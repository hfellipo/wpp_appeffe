<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiHttpClient;
use App\Services\WhatsAppEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WhatsAppInboxController extends Controller
{
    private EvolutionApiHttpClient $client;
    private WhatsAppEventPublisher $events;

    public function __construct(EvolutionApiHttpClient $client, WhatsAppEventPublisher $events)
    {
        $this->client = $client;
        $this->events = $events;
    }

    public function index(): View
    {
        return view('whatsapp.inbox');
    }

    public function conversations(): JsonResponse
    {
        $accountId = auth()->user()->accountId();

        $items = WhatsAppConversation::query()
            ->where('user_id', $accountId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get([
                'public_id',
                'instance_name',
                'contact_number',
                'contact_name',
                'last_message_at',
                'last_message_preview',
                'unread_count',
            ]);

        // Best-effort avatar/name enrichment from whatsapp_contacts (single query)
        $keys = $items
            ->map(function (WhatsAppConversation $c) {
                $num = preg_replace('/\D/', '', (string) $c->contact_number) ?: '';
                return $c->instance_name . '|' . $num;
            })
            ->filter()
            ->unique()
            ->values();

        $contactsByKey = [];
        if ($keys->count() > 0) {
            $instances = $items->pluck('instance_name')->filter()->unique()->values();
            $numbers = $items->pluck('contact_number')->map(fn ($v) => preg_replace('/\D/', '', (string) $v) ?: null)->filter()->unique()->values();

            $contacts = WhatsAppContact::query()
                ->where('user_id', $accountId)
                ->whereIn('instance_name', $instances)
                ->whereIn('contact_number', $numbers)
                ->get(['instance_name', 'contact_number', 'display_name', 'avatar_url']);

            foreach ($contacts as $ct) {
                $k = (string) $ct->instance_name . '|' . (string) (preg_replace('/\D/', '', (string) $ct->contact_number) ?: '');
                $contactsByKey[$k] = $ct;
            }
        }

        return response()->json([
            'success' => true,
            // Do not expose internal numeric IDs
            'items' => $items->map(function (WhatsAppConversation $c) use ($contactsByKey) {
                $k = $c->instance_name . '|' . (preg_replace('/\D/', '', (string) $c->contact_number) ?: '');
                $ct = $k !== '|' ? ($contactsByKey[$k] ?? null) : null;

                return [
                    'id' => $c->public_id,
                    'instance_name' => $c->instance_name,
                    'contact_number' => $c->contact_number,
                    'contact_name' => $c->contact_name ?: (is_object($ct) ? ($ct->display_name ?: null) : null),
                    'avatar_url' => is_object($ct) ? ($ct->avatar_url ?: null) : null,
                    'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                    'last_message_preview' => $c->last_message_preview,
                    'unread_count' => (int) $c->unread_count,
                ];
            })->values(),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $accountId = auth()->user()->accountId();
        $search = (string) $request->query('search', '');

        $q = Contact::query()
            ->forUser($accountId)
            ->orderBy('name');

        if ($search !== '') {
            $q->search($search);
        }

        $items = $q->limit(80)->get(['id', 'name', 'phone', 'email']);

        return response()->json([
            'success' => true,
            'items' => $items->map(function (Contact $c) {
                $digits = preg_replace('/\D/', '', (string) $c->phone) ?: '';
                return [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'raw_phone' => $digits,
                    // WhatsApp usually expects country code. Default Brazil (+55) when missing.
                    'wa_phone' => $this->normalizeWhatsappNumber($digits),
                ];
            })->values(),
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $accountId = auth()->user()->accountId();

        $request->validate([
            'contact_id' => 'required|integer',
        ]);

        /** @var Contact|null $contact */
        $contact = Contact::query()
            ->forUser($accountId)
            ->where('id', (int) $request->input('contact_id'))
            ->first(['id', 'name', 'phone']);

        if (! $contact) {
            return response()->json(['success' => false, 'error' => 'Contato não encontrado.'], 404);
        }

        $number = preg_replace('/\D/', '', (string) $contact->phone) ?: '';
        if ($number === '') {
            return response()->json(['success' => false, 'error' => 'Contato inválido (sem número).'], 422);
        }
        $waNumber = $this->normalizeWhatsappNumber($number);
        if ($waNumber === '') {
            return response()->json(['success' => false, 'error' => 'Contato inválido (sem número).'], 422);
        }

        // Choose best instance for this account (prefer connected)
        $connectedStates = ['open', 'connected', 'online', 'ready'];
        $wa = WhatsAppInstance::query()
            ->where('user_id', $accountId)
            ->whereIn('status', $connectedStates)
            ->orderByDesc('updated_at')
            ->first(['id', 'instance_name', 'status']);

        if (! $wa) {
            // Fallback to latest instance, even if status is stale
            $wa = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->orderByDesc('updated_at')
                ->first(['id', 'instance_name', 'status']);
        }

        if (! $wa) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhuma instância WhatsApp encontrada. Configure e conecte em /settings/whatsapp.',
            ], 409);
        }

        $instance = preg_replace('/\D/', '', (string) $wa->instance_name);
        if ($instance === '') {
            return response()->json(['success' => false, 'error' => 'Instância inválida.'], 422);
        }

        $peerJid = $waNumber . '@s.whatsapp.net';
        $legacyPeerJid = $number . '@s.whatsapp.net';

        // Reuse existing conversation even if it was created without country code (legacy)
        $existing = WhatsAppConversation::query()
            ->where('user_id', (int) $accountId)
            ->where('instance_name', $instance)
            ->whereIn('peer_jid', array_values(array_unique([$peerJid, $legacyPeerJid])))
            ->first();

        if ($existing) {
            // Best-effort: keep display name up to date
            if (!$existing->contact_name) {
                $existing->contact_name = $contact->name;
                $existing->save();
            }
            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $existing->public_id,
                    'instance_name' => $existing->instance_name,
                    'contact_number' => $existing->contact_number ?: $waNumber,
                    'contact_name' => $existing->contact_name,
                    'avatar_url' => null,
                    'last_message_at' => optional($existing->last_message_at)->toIso8601String(),
                    'last_message_preview' => $existing->last_message_preview,
                    'unread_count' => (int) $existing->unread_count,
                ],
            ]);
        }

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            [
                'user_id' => (int) $accountId,
                'instance_name' => $instance,
                'peer_jid' => $peerJid,
            ],
            [
                'kind' => 'direct',
                'contact_number' => $waNumber,
                'contact_name' => $contact->name,
                'last_message_at' => null,
                'last_message_preview' => null,
                'unread_count' => 0,
            ]
        );

        // Publish event so other tabs update immediately
        $this->events->publish((int) $accountId, 'wa.conversation.created', [
            'conversation' => [
                'id' => $conversation->public_id,
                'instance_name' => $conversation->instance_name,
                'contact_number' => $conversation->contact_number,
                'contact_name' => $conversation->contact_name,
                'avatar_url' => null,
                'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                'last_message_preview' => $conversation->last_message_preview,
                'unread_count' => (int) $conversation->unread_count,
            ],
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->public_id,
                'instance_name' => $conversation->instance_name,
                'contact_number' => $conversation->contact_number,
                'contact_name' => $conversation->contact_name,
                'avatar_url' => null,
                'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                'last_message_preview' => $conversation->last_message_preview,
                'unread_count' => (int) $conversation->unread_count,
            ],
        ]);
    }

    /**
     * Return avatar URL for a conversation. If not in DB, fetch from Evolution API and cache.
     */
    public function avatar(WhatsAppConversation $conversation): JsonResponse
    {
        $accountId = auth()->user()->accountId();
        if ((int) $conversation->user_id !== (int) $accountId) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $instance = preg_replace('/\D/', '', (string) $conversation->instance_name);
        $num = preg_replace('/\D/', '', (string) $conversation->contact_number) ?: '';
        if ($instance === '' || $num === '') {
            return response()->json(['success' => true, 'avatar_url' => null]);
        }

        $contact = WhatsAppContact::query()
            ->where('user_id', $accountId)
            ->where('instance_name', $conversation->instance_name)
            ->where('contact_number', $num)
            ->first();

        if ($contact && $contact->avatar_url) {
            return response()->json(['success' => true, 'avatar_url' => $contact->avatar_url]);
        }

        if (!$this->client->isConfigured()) {
            return response()->json(['success' => true, 'avatar_url' => null]);
        }

        $peerJid = $conversation->peer_jid ?: ($num . '@s.whatsapp.net');
        $resp = $this->client->fetchProfilePictureUrl($instance, $peerJid);
        $url = null;
        if ($resp['status'] >= 200 && $resp['status'] < 300 && is_array($resp['json'] ?? null)) {
            $url = $resp['json']['profilePictureUrl'] ?? $resp['json']['profilePicture'] ?? $resp['json']['url'] ?? null;
            $url = is_string($url) ? trim($url) : null;
            if ($url !== '' && $url !== null) {
                WhatsAppContact::query()->updateOrCreate(
                    [
                        'user_id' => $accountId,
                        'instance_name' => $conversation->instance_name,
                        'contact_number' => $num,
                    ],
                    ['avatar_url' => $url, 'contact_jid' => $peerJid]
                );
            }
        }

        return response()->json(['success' => true, 'avatar_url' => $url]);
    }

    public function messages(WhatsAppConversation $conversation): JsonResponse
    {
        $accountId = auth()->user()->accountId();
        if ((int) $conversation->user_id !== (int) $accountId) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $after = (string) request()->query('after', '');
        $before = (string) request()->query('before', '');
        $limit = (int) request()->query('limit', 200);
        $limit = max(1, min(200, $limit));

        $q = WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id);

        if ($after !== '') {
            // ULID is lexicographically sortable; use public_id cursor
            $q->where('public_id', '>', $after)->orderBy('public_id')->limit($limit);
        } elseif ($before !== '') {
            // Older messages (infinite scroll upwards)
            $q->where('public_id', '<', $before)->orderByDesc('public_id')->limit($limit);
        } else {
            $q->orderByDesc('public_id')->limit($limit);
        }

        $items = $q->get([
            'public_id',
            'direction',
            'message_type',
            'body',
            'status',
            'sent_at',
            'delivered_at',
            'read_at',
            'created_at',
        ]);

        if ($after === '') {
            $items = $items->reverse()->values();
        }

        // Mark as read when user opens/fetches this conversation
        if ((int) $conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();

            $this->events->publish((int) $accountId, 'wa.conversation.read', [
                'conversation_id' => $conversation->public_id,
                'unread_count' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            // Do not expose internal numeric IDs
            'items' => $items->map(function (WhatsAppMessage $m) {
                return [
                    'id' => $m->public_id,
                    'direction' => $m->direction,
                    'message_type' => $m->message_type,
                    'body' => $m->body,
                    'status' => $m->status,
                    'sent_at' => optional($m->sent_at)->toIso8601String(),
                    'delivered_at' => optional($m->delivered_at)->toIso8601String(),
                    'read_at' => optional($m->read_at)->toIso8601String(),
                    'created_at' => optional($m->created_at)->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }

    public function send(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        Log::channel('single')->info('WhatsApp send: request received', [
            'conversation_id' => $conversation->public_id,
        ]);

        try {
            $accountId = auth()->user()->accountId();
            if ((int) $conversation->user_id !== (int) $accountId) {
                return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
            }

            $request->validate([
                'text' => 'required|string|max:4000',
            ]);

            if (!$this->client->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
                ], 400);
            }

            $instance = preg_replace('/\D/', '', (string) $conversation->instance_name);
            if ($instance === '') {
                return response()->json(['success' => false, 'error' => 'Instância inválida.'], 422);
            }

            // Segurança: garantir que a instância pertence a esta conta
            $waInstance = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->where('instance_name', $instance)
                ->first();
            if (!$waInstance) {
                return response()->json(['success' => false, 'error' => 'Instância não encontrada para este usuário.'], 404);
            }

            // Evolution API espera "number" como JID completo (ex: 5511999999999@s.whatsapp.net ou grupo@g.us)
            $recipient = $this->recipientForEvolution($conversation);
            if ($recipient === '') {
                return response()->json(['success' => false, 'error' => 'Contato inválido.'], 422);
            }

            $text = (string) $request->input('text');

            // Evolution API (v2): POST /message/sendText/{instance}
            $payload = [
                'number' => $recipient,
                'text' => $text,
            ];
            $resp = $this->client->post("/message/sendText/{$instance}", $payload);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                Log::channel('single')->warning('Evolution sendText falhou', [
                    'instance' => $instance,
                    'recipient' => $recipient,
                    'http_status' => $resp['status'],
                    'response' => $resp['json'] ?? $resp['text'],
                ]);
                return response()->json([
                    'success' => false,
                    'http_status' => $resp['status'],
                    'error' => $this->evolutionErrorMessage($resp),
                    'details' => $resp['json'] ?? $resp['text'],
                ], $resp['status'] === 0 ? 502 : 503);
            }

            $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);

            $msg = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
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

            $avatarUrl = null;
            $displayName = null;
            $num = preg_replace('/\D/', '', (string) $conversation->contact_number) ?: '';
            if ($num !== '') {
                $ct = WhatsAppContact::query()
                    ->where('user_id', $accountId)
                    ->where('instance_name', $conversation->instance_name)
                    ->where('contact_number', $num)
                    ->first(['avatar_url', 'display_name']);
                if ($ct) {
                    $avatarUrl = $ct->avatar_url ?: null;
                    $displayName = $ct->display_name ?: null;
                }
            }

            $this->events->publish((int) $accountId, 'wa.message.created', [
                'conversation_id' => $conversation->public_id,
                'message' => [
                    'id' => $msg->public_id,
                    'direction' => $msg->direction,
                    'message_type' => $msg->message_type,
                    'body' => $msg->body,
                    'status' => $msg->status,
                    'sent_at' => optional($msg->sent_at)->toIso8601String(),
                    'delivered_at' => optional($msg->delivered_at)->toIso8601String(),
                    'read_at' => optional($msg->read_at)->toIso8601String(),
                    'created_at' => optional($msg->created_at)->toIso8601String(),
                ],
                'conversation' => [
                    'id' => $conversation->public_id,
                    'instance_name' => $conversation->instance_name,
                    'contact_number' => $conversation->contact_number,
                    'contact_name' => $conversation->contact_name ?: $displayName,
                    'avatar_url' => $avatarUrl,
                    'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                    'last_message_preview' => $conversation->last_message_preview,
                    'unread_count' => (int) $conversation->unread_count,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $msg->public_id,
                    'direction' => $msg->direction,
                    'message_type' => $msg->message_type,
                    'body' => $msg->body,
                    'status' => $msg->status,
                    'sent_at' => optional($msg->sent_at)->toIso8601String(),
                    'delivered_at' => optional($msg->delivered_at)->toIso8601String(),
                    'read_at' => optional($msg->read_at)->toIso8601String(),
                    'created_at' => optional($msg->created_at)->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('WhatsApp send exception', [
                'conversation_id' => $conversation->public_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao enviar mensagem. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Recipient identifier for Evolution API: full JID (number@s.whatsapp.net or group@g.us).
     */
    private function recipientForEvolution(WhatsAppConversation $conversation): string
    {
        $peerJid = trim((string) ($conversation->peer_jid ?? ''));
        if ($peerJid !== '' && str_contains($peerJid, '@')) {
            return $peerJid;
        }
        $number = $this->normalizeWhatsappNumber((string) $conversation->contact_number);
        if ($number === '') {
            return '';
        }
        return $number . '@s.whatsapp.net';
    }

    /**
     * @param array{status:int, json:array|null, text:string} $resp
     */
    private function evolutionErrorMessage(array $resp): string
    {
        if ($resp['status'] === 0) {
            return 'Não foi possível conectar à Evolution API. Verifique a URL e a rede.';
        }
        $json = $resp['json'] ?? null;
        if (is_array($json)) {
            $msg = $json['message'] ?? $json['error'] ?? $json['reason'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }
        return 'Falha ao enviar mensagem na Evolution.';
    }

    /**
     * Normalize a phone number for WhatsApp sending.
     * Default Brazil country code (+55) when missing (10/11 digits -> add 55).
     */
    private function normalizeWhatsappNumber(string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?: '';
        if ($digits === '') return '';

        // Already has BR country code and length looks like BR (55 + DDD + number)
        if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
            return $digits;
        }

        // Local BR number (DDD + number)
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55' . $digits;
        }

        // Fallback: return as-is
        return $digits;
    }

    /**
     * Extract Evolution/WhatsApp remote message id from sendText response.
     *
     * @param  array<string,mixed>|null  $json
     */
    private function extractEvolutionRemoteId(?array $json): string
    {
        if (!is_array($json)) return '';

        $candidates = [
            'key.id',
            'keyId',
            'id',
            'messageId',
            'message.id',
            'data.key.id',
            'data.id',
        ];

        foreach ($candidates as $path) {
            $v = Arr::get($json, $path);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return '';
    }
}

