<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Envio de mensagem WhatsApp para contato/conversa (igual ao chat).
 * Usado pelo inbox e pelas automações.
 */
class WhatsAppSendService
{
    private const NON_BRAZIL_TWO_DIGIT_COUNTRY_CODES = [
        '1', '7', '20', '27', '30', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45',
        '46', '47', '48', '49', '51', '52', '53', '54', '56', '57', '58', '60', '61', '62', '63', '64',
        '65', '66', '84', '86', '90', '92', '93', '94', '95', '98',
    ];

    public function __construct(
        private EvolutionApiHttpClient $client
    ) {
    }

    /**
     * Encontra ou cria conversa para o contato (igual ao startConversation do inbox).
     */
    public function findOrCreateConversationForContact(int $accountId, Contact $contact): ?WhatsAppConversation
    {
        $waNumber = $contact->phone_for_whatsapp;
        if ($waNumber === '') {
            return null;
        }

        $number = preg_replace('/\D/', '', (string) $contact->phone) ?: '';
        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        if ($number === '') {
            $number = str_starts_with($waNumber, '55') ? substr($waNumber, 2) : $waNumber;
        }

        // Round-robin: escolhe a instância ativa menos recentemente usada
        $wa = WhatsAppInstance::nextForUser($accountId);

        // Fallback: qualquer instância do usuário (mesmo desconectada)
        if (! $wa) {
            $wa = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->orderByDesc('updated_at')
                ->first(['id', 'instance_name', 'last_used_at']);
        }
        if (! $wa) {
            return null;
        }

        // Marca como usada agora para manter o round-robin
        $wa->markUsed();

        $instance = trim((string) $wa->instance_name) ?: preg_replace('/\D/', '', (string) $wa->instance_name);
        if ($instance === '') {
            return null;
        }

        $peerJid = $waNumber . '@s.whatsapp.net';
        $legacyPeerJid = $number . '@s.whatsapp.net';
        $wrongPeerJid = (strlen($number) === 11 && str_starts_with($number, '0'))
            ? ('550' . substr($number, 1) . '@s.whatsapp.net')
            : null;
        $peerJids = array_values(array_filter(array_unique([$peerJid, $legacyPeerJid, $wrongPeerJid])));

        $instanceDigits = preg_replace('/\D/', '', (string) $wa->instance_name);
        $existing = WhatsAppConversation::query()
            ->where('user_id', $accountId)
            ->where(function ($q) use ($instance, $instanceDigits) {
                $q->where('instance_name', $instance)
                    ->orWhere('instance_name', $instanceDigits);
            })
            ->whereIn('peer_jid', $peerJids)
            ->first();

        if ($existing) {
            if (empty($existing->contact_id) || (int) $existing->contact_id !== (int) $contact->id) {
                $existing->contact_id = $contact->id;
                $existing->save();
            }
            if (! $existing->contact_name) {
                $existing->contact_name = $contact->name;
                $existing->save();
            }
            $this->ensureWhatsAppContactFromAppContact($accountId, $instance, $waNumber, $peerJid, $contact->name);
            return $existing;
        }

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            [
                'user_id' => $accountId,
                'instance_name' => $instance,
                'peer_jid' => $peerJid,
            ],
            [
                'contact_id' => $contact->id,
                'kind' => 'direct',
                'contact_number' => $waNumber,
                'contact_name' => $contact->name,
                'last_message_at' => null,
                'last_message_preview' => null,
                'unread_count' => 0,
            ]
        );

        $this->ensureWhatsAppContactFromAppContact($accountId, $instance, $waNumber, $peerJid, $contact->name);
        return $conversation;
    }

    /**
     * Envia texto pela conversa (Evolution API + grava WhatsAppMessage).
     * Idêntico ao envio do chat. automationRunId opcional para histórico do contato.
     * source_type/source_id: ex. funnel_stage + stage_id para identificar no funil.
     */
    public function sendTextToConversation(
        WhatsAppConversation $conversation,
        string $text,
        ?int $automationRunId = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?WhatsAppMessage {
        if (! $this->client->isConfigured()) {
            Log::channel('single')->warning('WhatsAppSendService: Evolution API não configurada.');
            return null;
        }

        $accountId = (int) $conversation->user_id;
        $instanceNormalized = preg_replace('/\D/', '', (string) $conversation->instance_name);
        $waInstance = WhatsAppInstance::query()
            ->where('user_id', $accountId)
            ->where(function ($q) use ($instanceNormalized, $conversation) {
                $q->where('instance_name', $instanceNormalized)
                    ->orWhere('instance_name', $conversation->instance_name);
            })
            ->first();
        if (! $waInstance) {
            $candidates = WhatsAppInstance::query()->where('user_id', $accountId)->get(['instance_name']);
            foreach ($candidates as $c) {
                if (preg_replace('/\D/', '', (string) $c->instance_name) === $instanceNormalized) {
                    $waInstance = $c;
                    break;
                }
            }
        }
        if (! $waInstance) {
            Log::channel('single')->warning('WhatsAppSendService: instância não encontrada', [
                'conversation_id' => $conversation->id,
            ]);
            return null;
        }

        $instanceForApi = trim((string) $waInstance->instance_name) ?: $instanceNormalized;
        $recipient = $this->recipientForEvolution($conversation);
        if ($recipient === '') {
            $recipient = $this->buildRecipientFromConversation($conversation);
            if ($recipient !== '' && empty(trim((string) $conversation->peer_jid))) {
                $conversation->peer_jid = $recipient;
                $conversation->save();
            }
        }
        if ($recipient === '') {
            Log::channel('single')->warning('WhatsAppSendService: destinatário vazio', [
                'conversation_id' => $conversation->id,
            ]);
            return null;
        }

        $numberForPayload = $this->numberForEvolutionPayload($recipient);
        $payload = ['number' => $numberForPayload, 'text' => $text];
        $resp = $this->client->post("/message/sendText/{$instanceForApi}", $payload);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            Log::channel('single')->warning('WhatsAppSendService: Evolution sendText falhou', [
                'conversation_id' => $conversation->id,
                'http_status' => $resp['status'],
                'response' => $resp['json'] ?? $resp['text'],
            ]);
            return null;
        }

        $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
        $resolvedSourceType = $sourceType ?? ($automationRunId ? 'automation_run' : null);
        $resolvedSourceId = $sourceId ?? ($automationRunId ? $automationRunId : null);
        $msg = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'automation_run_id' => $automationRunId,
            'source_type' => $resolvedSourceType,
            'source_id' => $resolvedSourceId,
            'direction' => 'out',
            'message_type' => 'text',
            'body' => $text,
            'remote_id' => $remoteId ?: null,
            'status' => 'sent',
            'sent_at' => now(),
            'raw_payload' => is_array($resp['json']) ? $resp['json'] : null,
        ]);

        $conversation->last_message_at = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = mb_substr($text, 0, 500);
        $conversation->save();

        return $msg;
    }

    /**
     * Envia mensagem para um contato (resolve conversa + envia). Para automações/funil.
     */
    public function sendTextToContact(
        int $accountId,
        Contact $contact,
        string $text,
        ?int $automationRunId = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?WhatsAppMessage {
        $conversation = $this->findOrCreateConversationForContact($accountId, $contact);
        if (! $conversation) {
            return null;
        }
        return $this->sendTextToConversation($conversation, $text, $automationRunId, $sourceType, $sourceId);
    }

    /**
     * Envia imagem (ou mídia) com legenda pela conversa. Path = caminho no disk 'local' (ex: scheduled_posts/xxx.jpg).
     */
    public function sendMediaToConversation(
        WhatsAppConversation $conversation,
        string $storagePath,
        string $mimeType,
        string $caption,
        ?int $automationRunId = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?WhatsAppMessage {
        if (! $this->client->isConfigured()) {
            Log::channel('single')->warning('WhatsAppSendService: Evolution API não configurada.');
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($storagePath)) {
            Log::channel('single')->warning('WhatsAppSendService: arquivo de mídia não encontrado', ['path' => $storagePath]);
            return null;
        }

        $accountId = (int) $conversation->user_id;
        $instanceNormalized = preg_replace('/\D/', '', (string) $conversation->instance_name);
        $waInstance = WhatsAppInstance::query()
            ->where('user_id', $accountId)
            ->where(function ($q) use ($instanceNormalized, $conversation) {
                $q->where('instance_name', $instanceNormalized)
                    ->orWhere('instance_name', $conversation->instance_name);
            })
            ->first();
        if (! $waInstance) {
            $candidates = WhatsAppInstance::query()->where('user_id', $accountId)->get(['instance_name']);
            foreach ($candidates as $c) {
                if (preg_replace('/\D/', '', (string) $c->instance_name) === $instanceNormalized) {
                    $waInstance = $c;
                    break;
                }
            }
        }
        if (! $waInstance) {
            Log::channel('single')->warning('WhatsAppSendService: instância não encontrada', ['conversation_id' => $conversation->id]);
            return null;
        }

        $instanceForApi = trim((string) $waInstance->instance_name) ?: $instanceNormalized;
        $recipient = $this->recipientForEvolution($conversation);
        if ($recipient === '') {
            $recipient = $this->buildRecipientFromConversation($conversation);
        }
        if ($recipient === '') {
            Log::channel('single')->warning('WhatsAppSendService: destinatário vazio', ['conversation_id' => $conversation->id]);
            return null;
        }

        $numberForPayload = $this->numberForEvolutionPayload($recipient);
        $mediatype = 'document';
        if (str_starts_with($mimeType, 'image/')) {
            $mediatype = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $mediatype = 'video';
        }

        $contents = $disk->get($storagePath);
        $mediaValue = base64_encode($contents);
        $fileName = basename($storagePath);

        $payload = [
            'number' => $numberForPayload,
            'mediatype' => $mediatype,
            'mimetype' => $mimeType,
            'media' => $mediaValue,
            'fileName' => $fileName,
        ];
        if ($caption !== '') {
            $payload['caption'] = mb_substr($caption, 0, 1024);
        }

        $resp = $this->client->sendMedia($instanceForApi, $payload);
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            Log::channel('single')->warning('WhatsAppSendService: Evolution sendMedia falhou', [
                'conversation_id' => $conversation->id,
                'http_status' => $resp['status'],
                'response' => $resp['json'] ?? $resp['text'],
            ]);
            return null;
        }

        $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
        $resolvedSourceType = $sourceType ?? ($automationRunId ? 'automation_run' : null);
        $resolvedSourceId = $sourceId ?? ($automationRunId ? $automationRunId : null);
        $msg = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'automation_run_id' => $automationRunId,
            'source_type' => $resolvedSourceType,
            'source_id' => $resolvedSourceId,
            'direction' => 'out',
            'message_type' => $mediatype,
            'body' => $caption,
            'remote_id' => $remoteId ?: null,
            'status' => 'sent',
            'sent_at' => now(),
            'raw_payload' => is_array($resp['json']) ? $resp['json'] : null,
        ]);

        $conversation->last_message_at = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = $caption !== '' ? mb_substr($caption, 0, 500) : ($mediatype === 'image' ? 'Foto' : 'Mídia');
        $conversation->save();

        return $msg;
    }

    /**
     * Envia mídia com legenda para um contato (resolve conversa + envia).
     */
    public function sendMediaToContact(
        int $accountId,
        Contact $contact,
        string $storagePath,
        string $mimeType,
        string $caption,
        ?int $automationRunId = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?WhatsAppMessage {
        $conversation = $this->findOrCreateConversationForContact($accountId, $contact);
        if (! $conversation) {
            return null;
        }
        return $this->sendMediaToConversation($conversation, $storagePath, $mimeType, $caption, $automationRunId, $sourceType, $sourceId);
    }

    /**
     * Envia mídia a partir de URL pública (imagem, vídeo, áudio, documento) para um contato.
     * Tipo: 'image' | 'video' | 'audio' | 'document'
     */
    public function sendMediaUrlToContact(
        int $accountId,
        Contact $contact,
        string $mediaUrl,
        string $mediaType,
        string $caption = '',
        string $fileName = '',
        ?int $automationRunId = null
    ): ?WhatsAppMessage {
        if (! $this->client->isConfigured()) {
            return null;
        }
        $conversation = $this->findOrCreateConversationForContact($accountId, $contact);
        if (! $conversation) {
            return null;
        }

        $waInstance = $this->resolveInstance($conversation);
        if (! $waInstance) {
            return null;
        }
        $instanceForApi = trim((string) $waInstance->instance_name);
        $recipient = $this->recipientForEvolution($conversation);
        if ($recipient === '') {
            return null;
        }
        $numberForPayload = $this->numberForEvolutionPayload($recipient);

        // Resolve media: if file is on our server, read from disk and send as base64
        // (more reliable than URL — Evolution API doesn't need to make outbound request)
        [$mediaValue, $mimeType] = $this->resolveMediaForSend($mediaUrl, $mediaType);

        if ($mediaType === 'audio') {
            $payload = ['number' => $numberForPayload, 'audio' => $mediaValue, 'encoding' => true];
            $resp    = $this->client->sendWhatsAppAudio($instanceForApi, $payload);
        } else {
            $payload = [
                'number'    => $numberForPayload,
                'mediatype' => $mediaType,
                'mimetype'  => $mimeType,
                'media'     => $mediaValue,
                'fileName'  => $fileName ?: basename(parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl),
            ];
            if ($caption !== '') {
                $payload['caption'] = mb_substr($caption, 0, 1024);
            }
            $resp = $this->client->sendMedia($instanceForApi, $payload);
        }

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            Log::channel('single')->warning('WhatsAppSendService: sendMediaUrl falhou', [
                'conversation_id' => $conversation->id,
                'http_status'     => $resp['status'],
                'media_url'       => $mediaUrl,
                'media_type'      => $mediaType,
                'response_text'   => mb_substr((string) ($resp['text'] ?? ''), 0, 500),
            ]);
            return null;
        }

        $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
        $msg = WhatsAppMessage::create([
            'conversation_id'   => $conversation->id,
            'automation_run_id' => $automationRunId,
            'source_type'       => $automationRunId ? 'automation_run' : null,
            'source_id'         => $automationRunId,
            'direction'         => 'out',
            'message_type'      => $mediaType,
            'body'              => $caption,
            'remote_id'         => $remoteId ?: null,
            'status'            => 'sent',
            'sent_at'           => now(),
            'raw_payload'       => is_array($resp['json']) ? $resp['json'] : null,
        ]);
        $conversation->last_message_at      = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = $caption ?: ucfirst($mediaType);
        $conversation->save();

        return $msg;
    }

    /**
     * Envia mensagem com botões interativos para um contato.
     * $buttons = [['id' => '1', 'text' => 'Opção 1'], ...]
     */
    public function sendButtonsToContact(
        int $accountId,
        Contact $contact,
        string $text,
        array $buttons,
        ?int $automationRunId = null
    ): ?WhatsAppMessage {
        if (! $this->client->isConfigured()) {
            return null;
        }
        $conversation = $this->findOrCreateConversationForContact($accountId, $contact);
        if (! $conversation) {
            return null;
        }
        $waInstance = $this->resolveInstance($conversation);
        if (! $waInstance) {
            return null;
        }
        $instanceForApi   = trim((string) $waInstance->instance_name);
        $recipient        = $this->recipientForEvolution($conversation);
        $numberForPayload = $this->numberForEvolutionPayload($recipient);

        $payload = [
            'number'      => $numberForPayload,
            'description' => $text,
            'buttons'     => array_map(fn ($b) => [
                'type'        => 'reply',
                'displayText' => $b['text'] ?? '',
                'id'          => $b['id']   ?? uniqid(),
            ], $buttons),
        ];
        $resp = $this->client->post("/message/sendButtons/{$instanceForApi}", $payload);
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            return null;
        }
        $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
        $msg = WhatsAppMessage::create([
            'conversation_id'   => $conversation->id,
            'automation_run_id' => $automationRunId,
            'source_type'       => $automationRunId ? 'automation_run' : null,
            'source_id'         => $automationRunId,
            'direction'         => 'out',
            'message_type'      => 'buttons',
            'body'              => $text,
            'remote_id'         => $remoteId ?: null,
            'status'            => 'sent',
            'sent_at'           => now(),
        ]);
        $conversation->last_message_at      = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = mb_substr($text, 0, 500);
        $conversation->save();

        return $msg;
    }

    /**
     * Envia mensagem de lista interativa para um contato.
     * $sections = [['title' => '', 'rows' => [['id' => '1', 'title' => 'Item']]]]
     */
    public function sendListToContact(
        int $accountId,
        Contact $contact,
        string $text,
        string $buttonText,
        array $sections,
        ?int $automationRunId = null
    ): ?WhatsAppMessage {
        if (! $this->client->isConfigured()) {
            return null;
        }
        $conversation = $this->findOrCreateConversationForContact($accountId, $contact);
        if (! $conversation) {
            return null;
        }
        $waInstance = $this->resolveInstance($conversation);
        if (! $waInstance) {
            return null;
        }
        $instanceForApi   = trim((string) $waInstance->instance_name);
        $recipient        = $this->recipientForEvolution($conversation);
        $numberForPayload = $this->numberForEvolutionPayload($recipient);

        $payload = [
            'number'      => $numberForPayload,
            'description' => $text,
            'buttonText'  => $buttonText,
            'sections'    => $sections,
        ];
        $resp = $this->client->post("/message/sendList/{$instanceForApi}", $payload);
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            return null;
        }
        $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
        $msg = WhatsAppMessage::create([
            'conversation_id'   => $conversation->id,
            'automation_run_id' => $automationRunId,
            'source_type'       => $automationRunId ? 'automation_run' : null,
            'source_id'         => $automationRunId,
            'direction'         => 'out',
            'message_type'      => 'list',
            'body'              => $text,
            'remote_id'         => $remoteId ?: null,
            'status'            => 'sent',
            'sent_at'           => now(),
        ]);
        $conversation->last_message_at      = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = mb_substr($text, 0, 500);
        $conversation->save();

        return $msg;
    }

    /** Resolve WhatsAppInstance from a conversation (shared helper). */
    private function resolveInstance(WhatsAppConversation $conversation): ?WhatsAppInstance
    {
        $accountId          = (int) $conversation->user_id;
        $instanceNormalized = preg_replace('/\D/', '', (string) $conversation->instance_name);
        $waInstance = WhatsAppInstance::query()
            ->where('user_id', $accountId)
            ->where(function ($q) use ($instanceNormalized, $conversation) {
                $q->where('instance_name', $instanceNormalized)
                  ->orWhere('instance_name', $conversation->instance_name);
            })
            ->first();
        if (! $waInstance) {
            foreach (WhatsAppInstance::query()->where('user_id', $accountId)->get(['instance_name']) as $c) {
                if (preg_replace('/\D/', '', (string) $c->instance_name) === $instanceNormalized) {
                    return $c;
                }
            }
        }
        return $waInstance;
    }

    private function ensureWhatsAppContactFromAppContact(
        int $userId,
        string $instanceName,
        string $contactNumberE164,
        string $contactJid,
        string $displayName
    ): void {
        $num = preg_replace('/\D/', '', $contactNumberE164) ?: $contactNumberE164;
        if ($num === '') {
            return;
        }
        $attrs = ['contact_jid' => $contactJid];
        $displayNameTrim = trim($displayName);
        if ($displayNameTrim !== '') {
            $existing = WhatsAppContact::query()
                ->where('user_id', $userId)
                ->where('instance_name', $instanceName)
                ->where('contact_number', $num)
                ->first(['display_name']);
            if (! $existing || $existing->display_name === null || $existing->display_name === '') {
                $attrs['display_name'] = $displayNameTrim;
            }
        }
        WhatsAppContact::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'instance_name' => $instanceName,
                'contact_number' => $num,
            ],
            $attrs
        );
    }

    private function buildRecipientFromConversation(WhatsAppConversation $conversation): string
    {
        $raw = trim((string) ($conversation->contact_number ?? ''));
        if ($raw === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) < 9) {
            return '';
        }
        $normalized = $this->normalizeWhatsappNumber($digits);
        if ($normalized === '' || strlen($normalized) < 9) {
            return '';
        }
        return $normalized . '@s.whatsapp.net';
    }

    private function recipientForEvolution(WhatsAppConversation $conversation): string
    {
        $peerJid = trim((string) ($conversation->peer_jid ?? ''));
        if ($peerJid !== '') {
            if (str_contains($peerJid, '@')) {
                return $this->normalizeJidForEvolution($peerJid);
            }
            $digits = preg_replace('/\D/', '', $peerJid);
            if ($digits !== '') {
                $normalized = $this->normalizeWhatsappNumber($digits);
                return $normalized !== '' ? $normalized . '@s.whatsapp.net' : $digits . '@s.whatsapp.net';
            }
        }
        $number = $this->normalizeWhatsappNumber((string) ($conversation->contact_number ?? ''));
        return $number !== '' ? $number . '@s.whatsapp.net' : '';
    }

    private function normalizeJidForEvolution(string $jid): string
    {
        if (! str_contains($jid, '@s.whatsapp.net')) {
            return $jid;
        }
        $digits = preg_replace('/\D/', '', explode('@', $jid)[0]) ?: '';
        if ($digits === '') {
            return $jid;
        }
        $normalized = $this->normalizeWhatsappNumber($digits);
        return $normalized !== '' ? $normalized . '@s.whatsapp.net' : $jid;
    }

    private function numberForEvolutionPayload(string $recipient): string
    {
        if (str_ends_with($recipient, '@s.whatsapp.net')) {
            $digits = preg_replace('/\D/', '', explode('@', $recipient)[0]) ?: '';
            if ($digits === '') {
                return $recipient;
            }
            $normalized = $this->normalizeWhatsappNumber($digits);
            return $normalized !== '' ? $normalized : $digits;
        }
        return $recipient;
    }

    private function normalizeWhatsappNumber(string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?: '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (str_starts_with($digits, '550') && strlen($digits) >= 13) {
            $digits = '55' . substr($digits, 3);
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 13) {
            $after55 = substr($digits, 2, 2);
            if (in_array($after55, self::NON_BRAZIL_TWO_DIGIT_COUNTRY_CODES, true)) {
                return substr($digits, 2);
            }
        }
        if (strlen($digits) >= 12 && strlen($digits) <= 15) {
            return $digits;
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            return $digits;
        }
        if ($this->isBrazilianLocalNumber($digits)) {
            return '55' . $digits;
        }
        if (strlen($digits) >= 9 && strlen($digits) <= 15) {
            return $digits;
        }
        return $digits;
    }

    private function isBrazilianLocalNumber(string $digits): bool
    {
        $len = strlen($digits);
        if ($len !== 10 && $len !== 11) {
            return false;
        }
        $prefix2 = substr($digits, 0, 2);
        if (in_array($prefix2, self::NON_BRAZIL_TWO_DIGIT_COUNTRY_CODES, true)) {
            return false;
        }
        $ddd = (int) $prefix2;
        return $ddd >= 11 && $ddd <= 99;
    }

    private function extractEvolutionRemoteId(?array $json): string
    {
        if (! is_array($json)) {
            return '';
        }
        $candidates = ['key.id', 'keyId', 'id', 'messageId', 'message.id', 'data.key.id', 'data.id'];
        foreach ($candidates as $path) {
            $v = Arr::get($json, $path);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        return '';
    }

    /**
     * Resolve a media URL for sending:
     * - If it's a file on our public storage → read from disk and return base64
     * - Otherwise → return the URL as-is
     * Returns [mediaValue, mimeType]
     */
    private function resolveMediaForSend(string $mediaUrl, string $mediaType): array
    {
        $appUrl = rtrim(config('app.url'), '/');

        // Check if the URL points to our own public storage
        $storagePrefix = $appUrl . '/storage/';
        if (str_starts_with($mediaUrl, $storagePrefix)) {
            $relativePath = substr($mediaUrl, strlen($storagePrefix));
            if (Storage::disk('public')->exists($relativePath)) {
                $contents = Storage::disk('public')->get($relativePath);
                $mime     = Storage::disk('public')->mimeType($relativePath)
                    ?: $this->guessMimeType($mediaUrl, $mediaType);
                return [base64_encode($contents), $mime];
            }
        }

        // External URL — use as-is with guessed MIME type
        return [$mediaUrl, $this->guessMimeType($mediaUrl, $mediaType)];
    }

    private function guessMimeType(string $url, string $mediaType): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'mp4'         => 'video/mp4',
            'mov'         => 'video/quicktime',
            'avi'         => 'video/x-msvideo',
            'mp3'         => 'audio/mpeg',
            'ogg'         => 'audio/ogg',
            'wav'         => 'audio/wav',
            'm4a'         => 'audio/mp4',
            'pdf'         => 'application/pdf',
            'doc'         => 'application/msword',
            'docx'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'         => 'application/vnd.ms-excel',
            'xlsx'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip'         => 'application/zip',
            default       => match ($mediaType) {
                'image'    => 'image/jpeg',
                'video'    => 'video/mp4',
                'audio'    => 'audio/mpeg',
                default    => 'application/octet-stream',
            },
        };
    }
}
