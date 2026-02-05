<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\WhatsAppAttachment;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function conversations(Request $request): JsonResponse
    {
        $accountId = auth()->user()->accountId();

        $q = WhatsAppConversation::query()
            ->where('user_id', $accountId);

        $kind = $request->query('kind');
        if ($kind === 'direct' || $kind === 'group') {
            $q->where(function ($query) use ($kind) {
                if ($kind === 'group') {
                    $query->where('kind', 'group');
                } else {
                    $query->whereNull('kind')->orWhere('kind', 'direct');
                }
            });
        }

        $items = $q->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(150)
            ->get([
                'public_id',
                'instance_name',
                'kind',
                'peer_jid',
                'contact_number',
                'contact_name',
                'last_message_at',
                'last_message_preview',
                'last_message_sender',
                'unread_count',
            ]);

        // Deduplicar: mesmo número/grupo = uma única conversa (mantém a mais recente)
        $seen = [];
        $items = $items->filter(function (WhatsAppConversation $c) use (&$seen) {
            $key = $c->instance_name . '|' . ($c->kind === 'group' ? $c->peer_jid : ltrim(preg_replace('/\D/', '', (string) ($c->contact_number ?? '')), '0'));
            if ($key === '|') {
                return true;
            }
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        })->values();

        // Group names from whatsapp_groups (group subject)
        $groupConversations = $items->filter(fn (WhatsAppConversation $c) => ($c->kind ?? '') === 'group');
        $groupsByJid = [];
        if ($groupConversations->isNotEmpty()) {
            $groupJids = $groupConversations->pluck('peer_jid')->filter()->unique()->values()->all();
            $groups = \App\Models\WhatsAppGroup::query()
                ->where('user_id', $accountId)
                ->whereIn('instance_name', $items->pluck('instance_name')->unique())
                ->whereIn('group_jid', $groupJids)
                ->get(['instance_name', 'group_jid', 'subject']);
            foreach ($groups as $g) {
                $groupsByJid[$g->instance_name . '|' . $g->group_jid] = $g->subject;
            }
        }

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

        // Nome do dono do número: prioridade tabela contacts (app). Parear por número em todos os formatos.
        $appContactNamesByKey = [];
        $directItems = $items->filter(fn (WhatsAppConversation $c) => ($c->kind ?? '') !== 'group');
        if ($directItems->isNotEmpty()) {
            $allContacts = Contact::forUser($accountId)->get(['name', 'phone']);
            foreach ($directItems as $c) {
                $convDigits = preg_replace('/\D/', '', (string) ($c->contact_number ?? ''));
                $convDigits = ltrim($convDigits, '0');
                if ($convDigits === '') continue;
                foreach ($allContacts as $appCt) {
                    $contactDigits = preg_replace('/\D/', '', (string) ($appCt->phone ?? ''));
                    $contactDigits = ltrim($contactDigits, '0');
                    if ($contactDigits === '') continue;
                    if (!$this->conversationPhoneMatchesContact($convDigits, $contactDigits)) {
                        continue;
                    }
                    $name = trim((string) ($appCt->name ?? ''));
                    if ($name === '') continue;
                    $inst = $c->instance_name;
                    $appContactNamesByKey[$inst . '|' . $convDigits] = $name;
                    $appContactNamesByKey[$inst . '|' . $contactDigits] = $name;
                    if (strlen($convDigits) >= 11) {
                        $appContactNamesByKey[$inst . '|' . substr($convDigits, -11)] = $name;
                    }
                    if (strlen($convDigits) >= 10) {
                        $appContactNamesByKey[$inst . '|' . substr($convDigits, -10)] = $name;
                    }
                    if (strlen($contactDigits) >= 11) {
                        $appContactNamesByKey[$inst . '|' . substr($contactDigits, -11)] = $name;
                    }
                    if (strlen($contactDigits) >= 10) {
                        $appContactNamesByKey[$inst . '|' . substr($contactDigits, -10)] = $name;
                    }
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            // Do not expose internal numeric IDs
            'items' => $items->map(function (WhatsAppConversation $c) use ($contactsByKey, $groupsByJid, $appContactNamesByKey) {
                $digits = preg_replace('/\D/', '', (string) ($c->contact_number ?? ''));
                $digitsNorm = $digits !== '' ? ltrim($digits, '0') : '';
                $k = $c->instance_name . '|' . $digits;
                $kNorm = $digitsNorm !== '' ? $c->instance_name . '|' . $digitsNorm : $k;
                $ct = $k !== '|' ? ($contactsByKey[$k] ?? null) : null;

                $isGroup = ($c->kind ?? '') === 'group';
                $displayName = null;
                if ($isGroup && $c->peer_jid) {
                    $groupKey = $c->instance_name . '|' . $c->peer_jid;
                    if (isset($groupsByJid[$groupKey]) && $groupsByJid[$groupKey] !== '') {
                        $displayName = $groupsByJid[$groupKey];
                    }
                }
                if ($displayName === null && !$isGroup) {
                    // Direto: nome do DONO do número. Ordem: (1) tabela contacts, (2) whatsapp_contacts, (3) conversation.contact_name
                    $appName = $appContactNamesByKey[$k] ?? $appContactNamesByKey[$kNorm] ?? null;
                    if ($appName === null && $digitsNorm !== '' && strlen($digitsNorm) >= 11) {
                        $appName = $appContactNamesByKey[$c->instance_name . '|' . substr($digitsNorm, -11)] ?? null;
                    }
                    if ($appName === null && $digitsNorm !== '' && strlen($digitsNorm) >= 10) {
                        $appName = $appContactNamesByKey[$c->instance_name . '|' . substr($digitsNorm, -10)] ?? null;
                    }
                    $waDisplayName = is_object($ct) ? ($ct->display_name ?? null) : null;
                    $convName = $c->contact_name ?: null;
                    $displayName = ($appName !== null && $appName !== '') ? $appName : (($waDisplayName !== null && $waDisplayName !== '') ? $waDisplayName : $convName);
                }
                if ($displayName === null && $isGroup) {
                    $displayName = $c->contact_name;
                }

                return [
                    'id' => $c->public_id,
                    'instance_name' => $c->instance_name,
                    'kind' => $c->kind ?: 'direct',
                    'peer_jid' => $c->peer_jid,
                    'contact_number' => $c->contact_number,
                    'contact_name' => $displayName,
                    'avatar_url' => is_object($ct) ? ($ct->avatar_url ?: null) : null,
                    'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                    'last_message_preview' => $c->last_message_preview,
                    'last_message_sender' => $c->last_message_sender,
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

        // Usa número já normalizado para WhatsApp (E.164), tratando (XX)XXXXX-XXXX do banco
        $waNumber = $contact->phone_for_whatsapp;
        if ($waNumber === '') {
            return response()->json(['success' => false, 'error' => 'Contato inválido (sem número).'], 422);
        }
        // Dígitos locais (sem 55) para buscar conversa existente por legacy JID
        $number = preg_replace('/\D/', '', (string) $contact->phone) ?: '';
        if (strlen($number) === 11 && str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        if ($number === '') {
            $number = str_starts_with($waNumber, '55') ? substr($waNumber, 2) : $waNumber;
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

        // Usa o nome real da instância (ex: "production" ou "5531973372872") para a conversa
        $instance = trim((string) $wa->instance_name) ?: preg_replace('/\D/', '', (string) $wa->instance_name);
        if ($instance === '') {
            return response()->json(['success' => false, 'error' => 'Instância inválida.'], 422);
        }

        $peerJid = $waNumber . '@s.whatsapp.net';
        $legacyPeerJid = $number . '@s.whatsapp.net';
        // JID errado que podia ser salvo antes do fix (zero após 55): 55031994234090@s.whatsapp.net
        $wrongPeerJid = (strlen($number) === 11 && str_starts_with($number, '0'))
            ? ('550' . substr($number, 1) . '@s.whatsapp.net')
            : null;

        $peerJids = array_values(array_filter(array_unique([$peerJid, $legacyPeerJid, $wrongPeerJid])));

        // Busca conversa existente: por instance_name real ou por dígitos (legacy)
        $instanceDigits = preg_replace('/\D/', '', (string) $wa->instance_name);
        $existing = WhatsAppConversation::query()
            ->where('user_id', (int) $accountId)
            ->where(function ($q) use ($instance, $instanceDigits) {
                $q->where('instance_name', $instance);
                if ($instanceDigits !== '') {
                    $q->orWhere('instance_name', $instanceDigits);
                }
            })
            ->whereIn('peer_jid', $peerJids)
            ->first();

        if ($existing) {
            $updated = false;
            if (!$existing->contact_name) {
                $existing->contact_name = $contact->name;
                $updated = true;
            }
            if (empty($existing->contact_id) || (int) $existing->contact_id !== (int) $contact->id) {
                $existing->contact_id = $contact->id;
                $updated = true;
            }
            if (empty(trim((string) $existing->peer_jid)) || $existing->peer_jid !== $peerJid) {
                // Corrige peer_jid vazio ou o formato antigo (55031...)
                $existing->peer_jid = $peerJid;
                $existing->contact_number = $existing->contact_number ?: $waNumber;
                $updated = true;
            }
            if ($updated) {
                $existing->save();
            }
            $this->ensureWhatsAppContactFromAppContact((int) $accountId, $instance, $waNumber, $peerJid, $contact->name);
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
                'contact_id' => $contact->id,
                'kind' => 'direct',
                'contact_number' => $waNumber,
                'contact_name' => $contact->name,
                'last_message_at' => null,
                'last_message_preview' => null,
                'unread_count' => 0,
            ]
        );

        // Registra o contato em whatsapp_contacts para o chat aparecer corretamente e avatar/nome funcionarem
        $this->ensureWhatsAppContactFromAppContact((int) $accountId, $instance, $waNumber, $peerJid, $contact->name);

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
     * Garante que exista registro em whatsapp_contacts quando a conversa vem da tabela contacts (app).
     * display_name = nome do DESTINATÁRIO; só preencher se ainda estiver vazio (nunca sobrescrever com nome de quem envia).
     */
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
            if (!$existing || $existing->display_name === null || $existing->display_name === '') {
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

    /**
     * Retorna o registro da tabela contacts que corresponde a esta conversa (por telefone).
     * Para grupos retorna found: false e is_group: true.
     */
    public function appContact(WhatsAppConversation $conversation): JsonResponse
    {
        $accountId = auth()->user()->accountId();
        if ((int) $conversation->user_id !== (int) $accountId) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (($conversation->kind ?? '') === 'group') {
            return response()->json([
                'success' => true,
                'found' => false,
                'is_group' => true,
                'message' => 'Conversa de grupo — não há contato único na tabela de contatos.',
            ]);
        }

        $convDigits = preg_replace('/\D/', '', (string) ($conversation->contact_number ?? ''));
        if ($convDigits === '') {
            return response()->json([
                'success' => true,
                'found' => false,
                'message' => 'Nenhum número associado a esta conversa.',
            ]);
        }

        $contact = $this->findContactByConversationPhone($accountId, $convDigits);
        if (!$contact) {
            return response()->json([
                'success' => true,
                'found' => false,
                'message' => 'Nenhum registro na tabela de contatos para este número.',
            ]);
        }

        $fieldValues = $contact->fieldValues()
            ->with('field:id,name,type')
            ->get()
            ->map(fn ($fv) => [
                'field_name' => $fv->field?->name ?? 'Campo',
                'value' => $fv->value,
                'formatted_value' => $fv->formatted_value ?? $fv->value,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'found' => true,
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'notes' => $contact->notes,
                'custom_fields' => $fieldValues,
            ],
        ]);
    }

    /**
     * Compara dígitos da conversa com dígitos do contato (tabela contacts) para pareamento.
     */
    private function conversationPhoneMatchesContact(string $convDigits, string $contactDigits): bool
    {
        if ($convDigits === $contactDigits) {
            return true;
        }
        if (strlen($convDigits) >= 10 && strlen($contactDigits) >= 10 && substr($convDigits, -10) === substr($contactDigits, -10)) {
            return true;
        }
        if (strlen($convDigits) >= 11 && strlen($contactDigits) >= 11 && substr($convDigits, -11) === substr($contactDigits, -11)) {
            return true;
        }
        if ($convDigits === '55' . $contactDigits || $contactDigits === '55' . $convDigits) {
            return true;
        }
        if (strlen($convDigits) >= 11 && $contactDigits === substr($convDigits, -11)) {
            return true;
        }
        if (strlen($convDigits) >= 10 && $contactDigits === substr($convDigits, -10)) {
            return true;
        }
        if (strlen($contactDigits) >= 11 && $convDigits === substr($contactDigits, -11)) {
            return true;
        }
        if (strlen($contactDigits) >= 10 && $convDigits === substr($contactDigits, -10)) {
            return true;
        }
        return false;
    }

    /**
     * Encontra um Contact da tabela contacts pelo número da conversa (comparação por dígitos).
     */
    private function findContactByConversationPhone(int $accountId, string $convDigits): ?Contact
    {
        $convDigits = ltrim($convDigits, '0');
        return Contact::forUser($accountId)
            ->get()
            ->first(function (Contact $c) use ($convDigits) {
                $d = preg_replace('/\D/', '', (string) ($c->phone ?? ''));
                $d = ltrim($d, '0');
                return $this->conversationPhoneMatchesContact($convDigits, $d);
            });
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

        $items = $q->with('attachments')->get([
            'id',
            'public_id',
            'direction',
            'sender_name',
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
            'items' => $items->map(function (WhatsAppMessage $m) use ($accountId) {
                $attachmentData = null;
                $first = $m->attachments->first();
                if ($first) {
                    $attachmentData = [
                        'id' => $first->public_id,
                        'type' => $first->type,
                        'mime' => $first->mime,
                        'size' => $first->size,
                        'caption_preview' => $first->caption_preview,
                        'remote_url' => $first->remote_url,
                        'url' => $this->attachmentUrl($first),
                    ];
                }
                return [
                    'id' => $m->public_id,
                    'direction' => $m->direction,
                    'sender_name' => $m->sender_name,
                    'message_type' => $m->message_type,
                    'body' => $m->body,
                    'status' => $m->status,
                    'sent_at' => optional($m->sent_at)->toIso8601String(),
                    'delivered_at' => optional($m->delivered_at)->toIso8601String(),
                    'read_at' => optional($m->read_at)->toIso8601String(),
                    'created_at' => optional($m->created_at)->toIso8601String(),
                    'attachment' => $attachmentData,
                ];
            })->values(),
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Serve attachment file (image/document) for the authenticated user.
     * Verifies ownership via message -> conversation -> user_id.
     */
    public function showAttachment(WhatsAppAttachment $attachment): JsonResponse|StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $accountId = auth()->user()->accountId();
        $attachment->load('message.conversation');
        $conversation = $attachment->message?->conversation;
        if (!$conversation || (int) $conversation->user_id !== (int) $accountId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($attachment->storage_path && $attachment->storage_disk) {
            $disk = Storage::disk($attachment->storage_disk);
            if (!$disk->exists($attachment->storage_path)) {
                return $this->attachmentNotFoundResponse();
            }
            $mime = $this->attachmentMime($attachment);
            $filename = $this->attachmentDownloadFilename($attachment);
            $disposition = $this->attachmentDisposition($mime, $attachment->type);
            return $disk->response($attachment->storage_path, $filename, [
                'Content-Type' => $mime,
            ], $disposition);
        }

        if ($attachment->remote_url) {
            return redirect()->away($attachment->remote_url);
        }

        // Imagem/vídeo/documento recebido sem URL: buscar na Evolution sob demanda (preview + clique para abrir)
        if ($this->resolveAttachmentFromEvolution($attachment)) {
            $attachment->refresh();
            if ($attachment->storage_path && $attachment->storage_disk) {
                $disk = Storage::disk($attachment->storage_disk);
                if ($disk->exists($attachment->storage_path)) {
                    $mime = $this->attachmentMime($attachment);
                    $filename = $this->attachmentDownloadFilename($attachment);
                    $disposition = $this->attachmentDisposition($mime, $attachment->type);
                    return $disk->response($attachment->storage_path, $filename, [
                        'Content-Type' => $mime,
                    ], $disposition);
                }
            }
        }

        return $this->attachmentNotFoundResponse();
    }

    /**
     * Resposta quando anexo não está disponível (evita download de JSON como "file.enc").
     */
    private function attachmentNotFoundResponse(): JsonResponse
    {
        return response()->json(['error' => 'Attachment not available'], 404, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Mime type para servir o anexo (fallback por tipo quando mime está vazio).
     */
    private function attachmentMime(WhatsAppAttachment $a): string
    {
        if ($a->mime && trim((string) $a->mime) !== '') {
            return trim($a->mime);
        }
        $t = strtolower((string) ($a->type ?? ''));
        return match ($t) {
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'audio' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }

    /**
     * inline = abre na aba (imagem, PDF). attachment = baixa com nome correto (documentos).
     */
    private function attachmentDisposition(string $mime, ?string $attachmentType): string
    {
        $m = strtolower($mime);
        $t = strtolower((string) $attachmentType);
        if (str_starts_with($m, 'image/') || str_starts_with($m, 'video/') || $t === 'image' || $t === 'video') {
            return 'inline';
        }
        if (str_contains($m, 'pdf')) {
            return 'inline';
        }
        return 'attachment';
    }

    /**
     * Busca mídia da Evolution (getBase64FromMediaMessage), salva em storage e atualiza o anexo.
     */
    private function resolveAttachmentFromEvolution(WhatsAppAttachment $attachment): bool
    {
        $message = $attachment->message;
        if (!$message || !$message->remote_id) {
            return false;
        }
        $conversation = $message->conversation;
        if (!$conversation) {
            return false;
        }
        $accountId = auth()->user()->accountId();
        if ((int) $conversation->user_id !== (int) $accountId) {
            return false;
        }
        if (!$this->client->isConfigured()) {
            return false;
        }

        $instanceName = trim((string) $conversation->instance_name);
        if ($instanceName === '') {
            return false;
        }

        $resp = $this->client->getBase64FromMediaMessage($instanceName, $message->remote_id);
        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            Log::channel('single')->debug('Evolution getBase64FromMediaMessage falhou', [
                'attachment_id' => $attachment->public_id,
                'remote_id' => $message->remote_id,
                'status' => $resp['status'],
            ]);
            return false;
        }

        $json = $resp['json'] ?? null;
        if (!is_array($json)) {
            return false;
        }

        $base64 = $json['base64'] ?? Arr::get($json, 'data.base64') ?? Arr::get($json, 'data') ?? Arr::get($json, 'response.base64') ?? null;
        if (!is_string($base64) || $base64 === '') {
            return false;
        }

        if (str_starts_with($base64, 'data:')) {
            $base64 = preg_replace('/^data:[^;]+;base64,/', '', $base64);
        }
        $contents = base64_decode($base64, true);
        if ($contents === false || $contents === '') {
            return false;
        }

        $mime = $attachment->mime ?: (Arr::get($json, 'mimetype') ?? 'application/octet-stream');
        $ext = $this->extensionFromMime((string) $mime, $attachment->type ?? 'document');
        $dir = 'whatsapp_attachments/'.now()->format('Y/m');
        $baseName = \Illuminate\Support\Str::ulid();
        $fileNameFromApi = is_string(Arr::get($json, 'fileName')) ? trim(Arr::get($json, 'fileName')) : null;
        if ($fileNameFromApi !== '' && $fileNameFromApi !== null) {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileNameFromApi);
            $baseName = pathinfo($safeName, PATHINFO_FILENAME) ?: $baseName;
        }
        $filename = $baseName.'.'.$ext;
        $storedPath = $dir.'/'.$filename;

        $disk = Storage::disk('local');
        if (!$disk->put($storedPath, $contents)) {
            return false;
        }

        $attachment->storage_disk = 'local';
        $attachment->storage_path = $storedPath;
        if (!$attachment->mime) {
            $attachment->mime = $mime;
        }
        $attachment->save();

        return true;
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

            // text aceita Unicode/emojis (UTF-8)
            $request->validate([
                'text' => 'required|string|max:4000',
            ]);

            if (!$this->client->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
                ], 400);
            }

            $instanceNormalized = preg_replace('/\D/', '', (string) $conversation->instance_name);

            // Instância da conta: busca por nome real (ex: "production") ou por dígitos (conversa vinda de contato)
            $waInstance = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->where(function ($q) use ($instanceNormalized, $conversation) {
                    $q->where('instance_name', $instanceNormalized)
                        ->orWhere('instance_name', $conversation->instance_name);
                })
                ->first();
            if (!$waInstance) {
                $candidates = WhatsAppInstance::query()
                    ->where('user_id', $accountId)
                    ->get(['id', 'instance_name']);
                foreach ($candidates as $c) {
                    if (preg_replace('/\D/', '', (string) $c->instance_name) === $instanceNormalized) {
                        $waInstance = $c;
                        break;
                    }
                }
            }
            if (!$waInstance) {
                Log::channel('single')->warning('WhatsApp send: instância não encontrada', [
                    'conversation_id' => $conversation->public_id,
                    'conversation_instance_name' => $conversation->instance_name,
                    'instance_normalized' => $instanceNormalized,
                ]);
                return response()->json(['success' => false, 'error' => 'Instância não encontrada para este usuário.'], 404);
            }
            // Evolution API espera o nome real da instância (ex: "production" ou "5511999999999"), não só dígitos
            $instanceForApi = trim((string) $waInstance->instance_name) ?: $instanceNormalized;

            // Log de diagnóstico quando envio vem de conversa (ex.: contato do banco)
            Log::channel('single')->info('WhatsApp send: payload', [
                'conversation_id' => $conversation->public_id,
                'instance_for_api' => $instanceForApi,
                'conversation_peer_jid' => $conversation->peer_jid,
                'conversation_contact_number' => $conversation->contact_number,
            ]);

            // Não bloquear por status em DB (pode estar desatualizado). Deixar a Evolution responder.

            // Evolution API espera "number" como JID completo (ex: 5511999999999@s.whatsapp.net ou grupo@g.us)
            $recipient = $this->recipientForEvolution($conversation);
            if ($recipient === '') {
                // Conversas vindas da lista de contatos às vezes têm peer_jid vazio ou em formato que não batia
                $recipient = $this->buildRecipientFromConversation($conversation);
                if ($recipient !== '') {
                    if (empty(trim((string) $conversation->peer_jid))) {
                        $conversation->peer_jid = $recipient;
                        $conversation->save();
                    }
                }
            }
            if ($recipient === '') {
                Log::channel('single')->warning('WhatsApp send: destinatário vazio', [
                    'conversation_id' => $conversation->public_id,
                    'peer_jid' => $conversation->peer_jid,
                    'contact_number' => $conversation->contact_number,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Esta conversa não tem número de destino. Selecione outro chat ou inicie uma nova conversa.',
                ], 422);
            }

            // Persiste JID e contact_number corrigidos (ex: 55+outro país -> só E.164; BR sem 55 -> com 55)
            $currentPeer = trim((string) ($conversation->peer_jid ?? ''));
            if ($currentPeer !== '' && $currentPeer !== $recipient && str_ends_with($recipient, '@s.whatsapp.net')) {
                $conversation->peer_jid = $recipient;
                $conversation->contact_number = preg_replace('/@.*/', '', $recipient);
                $conversation->save();
            }

            // Garante registro em whatsapp_contacts ao enviar (só contact_jid). Não passar nome para não sobrescrever display_name do destinatário.
            if (str_ends_with($recipient, '@s.whatsapp.net')) {
                $this->ensureWhatsAppContactFromAppContact(
                    (int) $accountId,
                    $conversation->instance_name,
                    $conversation->contact_number ?: preg_replace('/@.*/', '', $recipient),
                    $recipient,
                    ''
                );
            }

            $text = (string) $request->input('text');

            // Formato correto para Evolution em todos os envios (contacts, whatsapp_contacts, conversas recebidas)
            $numberForPayload = $this->numberForEvolutionPayload($recipient);

            $payload = [
                'number' => $numberForPayload,
                'text' => $text,
            ];
            $resp = $this->client->post("/message/sendText/{$instanceForApi}", $payload);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                Log::channel('single')->warning('Evolution sendText falhou', [
                    'conversation_id' => $conversation->public_id,
                    'instance' => $instanceForApi,
                    'recipient' => $recipient,
                    'conversation_peer_jid' => $conversation->peer_jid,
                    'conversation_contact_number' => $conversation->contact_number,
                    'http_status' => $resp['status'],
                    'response' => $resp['json'] ?? $resp['text'],
                ]);
                $errMsg = $this->evolutionErrorMessage($resp);
                $httpStatus = $resp['status'] === 0 ? 502 : ($resp['status'] >= 400 && $resp['status'] < 500 ? 422 : 503);
                $errPayload = [
                    'success' => false,
                    'http_status' => $resp['status'],
                    'error' => $errMsg,
                    'details' => $resp['json'] ?? $resp['text'],
                ];
                if (str_ends_with($recipient, '@s.whatsapp.net')) {
                    $errPayload['attempted_number_formatted'] = $this->formatJidForDisplay($recipient);
                }
                return response()->json($errPayload, $httpStatus);
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
            if (($conversation->kind ?? '') === 'group') {
                $conversation->last_message_sender = null; // our message, no "Sender: " in list
            }
            // Nunca alterar contact_name ao enviar: só atualizar last_message_*.
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
                    'sender_name' => $msg->sender_name,
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
                    'last_message_sender' => $conversation->last_message_sender,
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
                    'sender_name' => $msg->sender_name,
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
     * Limite de envio (KB). Evolution/WhatsApp: documentos até ~100MB; usar valor que o PHP permitir (upload_max_filesize).
     */
    private const SEND_MEDIA_MAX_KB = 64 * 1024; // 64MB

    /**
     * Send media (image, video or document) to the conversation via Evolution API.
     * Aceita todos os tipos: imagens (preview simples), vídeos e documentos (download).
     */
    public function sendMedia(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        try {
            $accountId = auth()->user()->accountId();
            if ((int) $conversation->user_id !== (int) $accountId) {
                return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
            }

            $request->validate([
                'file' => 'required|file|max:'. self::SEND_MEDIA_MAX_KB,
            ]);

            $file = $request->file('file');
            $caption = $request->input('caption', '');
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $originalName = $file->getClientOriginalName() ?: $file->hashName();

            // mediatype for Evolution: image | video | document
            $mediatype = 'document';
            if (str_starts_with((string) $mime, 'image/')) {
                $mediatype = 'image';
            } elseif (str_starts_with((string) $mime, 'video/')) {
                $mediatype = 'video';
            }

            if (!$this->client->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Evolution API não configurada.',
                ], 400);
            }

            $instanceNormalized = preg_replace('/\D/', '', (string) $conversation->instance_name);
            $waInstance = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->where(function ($q) use ($instanceNormalized, $conversation) {
                    $q->where('instance_name', $instanceNormalized)
                        ->orWhere('instance_name', $conversation->instance_name);
                })
                ->first();
            if (!$waInstance) {
                $candidates = WhatsAppInstance::query()->where('user_id', $accountId)->get(['instance_name']);
                foreach ($candidates as $c) {
                    if (preg_replace('/\D/', '', (string) $c->instance_name) === $instanceNormalized) {
                        $waInstance = $c;
                        break;
                    }
                }
            }
            if (!$waInstance) {
                return response()->json(['success' => false, 'error' => 'Instância não encontrada.'], 404);
            }
            $instanceForApi = trim((string) $waInstance->instance_name) ?: $instanceNormalized;

            $recipient = $this->recipientForEvolution($conversation);
            if ($recipient === '') {
                $recipient = $this->buildRecipientFromConversation($conversation);
            }
            if ($recipient === '') {
                return response()->json(['success' => false, 'error' => 'Não foi possível identificar o destinatário.'], 422);
            }

            $numberForPayload = $this->numberForEvolutionPayload($recipient);

            // Store file locally for our attachment record and future serving
            $disk = Storage::disk('local');
            $dir = 'whatsapp_attachments/'.now()->format('Y/m');
            $storedPath = $file->storeAs($dir, \Illuminate\Support\Str::ulid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName), ['disk' => 'local']);
            if (!$storedPath) {
                return response()->json(['success' => false, 'error' => 'Falha ao salvar arquivo.'], 500);
            }

            $contents = $disk->get($storedPath);
            $base64 = base64_encode($contents);
            // Evolution API expects raw base64 string, not data URL
            $mediaValue = $base64;

            $payload = [
                'number' => $numberForPayload,
                'mediatype' => $mediatype,
                'mimetype' => $mime,
                'media' => $mediaValue,
                'fileName' => $originalName,
            ];
            if ($caption !== '') {
                $payload['caption'] = mb_substr($caption, 0, 1024);
            }

            $resp = $this->client->sendMedia($instanceForApi, $payload);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                Log::channel('single')->warning('Evolution sendMedia falhou', [
                    'conversation_id' => $conversation->public_id,
                    'http_status' => $resp['status'],
                    'response' => $resp['json'] ?? $resp['text'],
                ]);
                $errMsg = $this->evolutionErrorMessage($resp);
                $httpStatus = $resp['status'] === 0 ? 502 : ($resp['status'] >= 400 && $resp['status'] < 500 ? 422 : 503);
                return response()->json([
                    'success' => false,
                    'error' => $errMsg,
                    'details' => $resp['json'] ?? $resp['text'],
                ], $httpStatus);
            }

            $remoteId = $this->extractEvolutionRemoteId($resp['json'] ?? null);
            // Body vazio quando sem legenda, para não exibir "[Imagem]" no balão
            $bodyForMessage = $caption !== '' ? $caption : '';
            $listPreview = $caption !== '' ? mb_substr($caption, 0, 500) : ($mediatype === 'image' ? 'Foto' : ($mediatype === 'video' ? 'Vídeo' : 'Documento'));

            $msg = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'message_type' => $mediatype,
                'body' => $bodyForMessage,
                'remote_id' => $remoteId ?: null,
                'status' => 'sent',
                'sent_at' => now(),
                'raw_payload' => is_array($resp['json']) ? $resp['json'] : null,
            ]);

            WhatsAppAttachment::create([
                'message_id' => $msg->id,
                'type' => $mediatype,
                'mime' => $mime,
                'size' => $file->getSize(),
                'storage_disk' => 'local',
                'storage_path' => $storedPath,
                'caption_preview' => $caption !== '' ? mb_substr($caption, 0, 500) : null,
            ]);

            $conversation->last_message_at = $msg->sent_at ?? $msg->created_at;
            $conversation->last_message_preview = $listPreview;
            if (($conversation->kind ?? '') === 'group') {
                $conversation->last_message_sender = null;
            }
            $conversation->save();

            $firstAttachment = $msg->attachments()->first();
            $attachmentData = null;
            if ($firstAttachment) {
                $attachmentData = [
                    'id' => $firstAttachment->public_id,
                    'type' => $firstAttachment->type,
                    'mime' => $firstAttachment->mime,
                    'size' => $firstAttachment->size,
                    'caption_preview' => $firstAttachment->caption_preview,
                    'remote_url' => $firstAttachment->remote_url,
                    'url' => $this->attachmentUrl($firstAttachment),
                ];
            }

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
                    'sender_name' => $msg->sender_name,
                    'message_type' => $msg->message_type,
                    'body' => $msg->body,
                    'status' => $msg->status,
                    'sent_at' => optional($msg->sent_at)->toIso8601String(),
                    'delivered_at' => optional($msg->delivered_at)->toIso8601String(),
                    'read_at' => optional($msg->read_at)->toIso8601String(),
                    'created_at' => optional($msg->created_at)->toIso8601String(),
                    'attachment' => $attachmentData,
                ],
                'conversation' => [
                    'id' => $conversation->public_id,
                    'instance_name' => $conversation->instance_name,
                    'contact_number' => $conversation->contact_number,
                    'contact_name' => $conversation->contact_name ?: $displayName,
                    'last_message_sender' => $conversation->last_message_sender,
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
                    'sender_name' => $msg->sender_name,
                    'message_type' => $msg->message_type,
                    'body' => $msg->body,
                    'status' => $msg->status,
                    'sent_at' => optional($msg->sent_at)->toIso8601String(),
                    'delivered_at' => optional($msg->delivered_at)->toIso8601String(),
                    'read_at' => optional($msg->read_at)->toIso8601String(),
                    'created_at' => optional($msg->created_at)->toIso8601String(),
                    'attachment' => $attachmentData,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('WhatsApp sendMedia exception', [
                'conversation_id' => $conversation->public_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao enviar mídia. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Tenta montar o JID a partir de contact_number quando peer_jid está vazio (ex.: conversas da lista de contatos).
     * Universal: só adiciona 55 quando for número local BR (DDD 11-99); demais países mantêm dígitos.
     */
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
        if (strlen($normalized) >= 10 && strlen($normalized) <= 15) {
            return $normalized . '@s.whatsapp.net';
        }
        return '';
    }

    /**
     * Recipient identifier for Evolution API: full JID (number@s.whatsapp.net or group@g.us).
     * Normaliza número BR (10/11 dígitos sem 55) para evitar "exists":false da Evolution.
     */
    private function recipientForEvolution(WhatsAppConversation $conversation): string
    {
        $peerJid = trim((string) ($conversation->peer_jid ?? ''));
        if ($peerJid !== '') {
            if (str_contains($peerJid, '@')) {
                return $this->normalizeJidForEvolution($peerJid);
            }
            // peer_jid às vezes vem só com dígitos (sem @s.whatsapp.net)
            $digits = preg_replace('/\D/', '', $peerJid);
            if ($digits !== '') {
                $normalized = $this->normalizeWhatsappNumber($digits);
                return $normalized !== '' ? $normalized . '@s.whatsapp.net' : $digits . '@s.whatsapp.net';
            }
        }
        $number = $this->normalizeWhatsappNumber((string) ($conversation->contact_number ?? ''));
        if ($number !== '') {
            return $number . '@s.whatsapp.net';
        }
        return '';
    }

    /**
     * Garante que JIDs de contato BR tenham código 55 (Evolution exige para "exists").
     * Ex: 31994234090@s.whatsapp.net -> 5531994234090@s.whatsapp.net
     */
    private function normalizeJidForEvolution(string $jid): string
    {
        if (!str_contains($jid, '@s.whatsapp.net')) {
            return $jid; // grupos ou outros formatos: devolve como está
        }
        $digits = preg_replace('/\D/', '', explode('@', $jid)[0]) ?: '';
        if ($digits === '') {
            return $jid;
        }
        $normalized = $this->normalizeWhatsappNumber($digits);
        return $normalized !== '' ? $normalized . '@s.whatsapp.net' : $jid;
    }

    /**
     * Formato do campo "number" no payload da Evolution para qualquer origem (contacts, whatsapp_contacts, webhook).
     * Contatos diretos: E.164 só dígitos (ex: 5531994234090). Grupos: JID completo (ex: 120363...@g.us).
     */
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

    /**
     * Formata JID para exibição. Brasil (55): (31) 99423-4090. Outros países: só dígitos (ex: 61403256156).
     */
    private function formatJidForDisplay(string $jid): string
    {
        $digits = preg_replace('/\D/', '', explode('@', $jid)[0]) ?: '';
        if ($digits === '') {
            return $jid;
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            $local = substr($digits, 2);
            $len = strlen($local);
            if ($len === 10) {
                return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 4), substr($local, 6, 4));
            }
            if ($len === 11) {
                return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 5), substr($local, 7, 4));
            }
        }
        return $digits;
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
            // Evolution retorna response.message[] com exists: false quando o número não tem WhatsApp
            $inner = $json['response'] ?? null;
            if (is_array($inner) && isset($inner['message']) && is_array($inner['message'])) {
                $first = $inner['message'][0] ?? null;
                if (is_array($first) && ($first['exists'] ?? null) === false) {
                    return 'Este número não está registrado no WhatsApp ou está incorreto. Confira o número (com DDD) e se o contato usa WhatsApp.';
                }
            }
            $msg = $json['message'] ?? $json['error'] ?? $json['reason'] ?? null;
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }
        return 'Falha ao enviar mensagem na Evolution.';
    }

    /**
     * Códigos de país de 2 dígitos (E.164) que não são DDD brasileiro. Se um número 10/11 dígitos
     * começa com um deles, não adicionamos 55 (ex: 61 Austrália). Não incluir 31, 21, etc. (DDD BR).
     */
    private const NON_BRAZIL_TWO_DIGIT_COUNTRY_CODES = [
        '1',  '7',  '20', '27', '30', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45',
        '46', '47', '48', '49', '51', '52', '53', '54', '56', '57', '58', '60', '61', '62', '63', '64',
        '65', '66', '84', '86', '90', '92', '93', '94', '95', '98',
    ];

    /**
     * Indica se os dígitos parecem número local brasileiro (DDD 11-99), e não outro país.
     */
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

    /**
     * Normaliza número para envio WhatsApp (E.164). Universal: qualquer país.
     * Só adiciona +55 quando for claramente número local BR (DDD 11-99). Demais: retorna dígitos como estão.
     */
    private function normalizeWhatsappNumber(string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?: '';
        if ($digits === '') {
            return '';
        }

        // Remove zero à esquerda (comum em vários países): 031994234090 -> 31994234090
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        // Corrige JID salvos errados no BR: 55031994234090 -> 5531994234090
        if (str_starts_with($digits, '550') && strlen($digits) >= 13) {
            $digits = '55' . substr($digits, 3);
        }
        // Corrige número salvo como 55 + outro país (ex: 5561403256156 Austrália -> 61403256156)
        if (str_starts_with($digits, '55') && strlen($digits) >= 13) {
            $after55 = substr($digits, 2, 2);
            if (in_array($after55, self::NON_BRAZIL_TWO_DIGIT_COUNTRY_CODES, true)) {
                return substr($digits, 2);
            }
        }

        // Já em E.164 (12-15 dígitos): qualquer país
        if (strlen($digits) >= 12 && strlen($digits) <= 15) {
            return $digits;
        }

        // Brasil: 55 + DDD 11-99 + 8 ou 9 dígitos
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            return $digits;
        }

        // Número local: só adiciona 55 se for padrão BR (DDD 11-99)
        if ($this->isBrazilianLocalNumber($digits)) {
            return '55' . $digits;
        }

        // Outros países: 9-15 dígitos sem alteração (E.164 ou nacional; Evolution aceita)
        if (strlen($digits) >= 9 && strlen($digits) <= 15) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Extensão do arquivo a partir do mime type (para documentos chegarem como .pdf, .docx, etc.).
     */
    private function extensionFromMime(string $mime, string $attachmentType): string
    {
        $m = strtolower($mime);
        if (str_starts_with($m, 'image/')) {
            return match (true) {
                str_contains($m, 'jpeg'), str_contains($m, 'jpg') => 'jpg',
                str_contains($m, 'png') => 'png',
                str_contains($m, 'gif') => 'gif',
                str_contains($m, 'webp') => 'webp',
                default => 'jpg',
            };
        }
        if (str_starts_with($m, 'video/')) {
            return str_contains($m, 'mp4') ? 'mp4' : (str_contains($m, 'webm') ? 'webm' : 'mp4');
        }
        return match (true) {
            str_contains($m, 'pdf') => 'pdf',
            str_contains($m, 'msword') && !str_contains($m, 'wordprocessingml') => 'doc',
            str_contains($m, 'wordprocessingml'), str_contains($m, 'document') => 'docx',
            str_contains($m, 'ms-excel') && !str_contains($m, 'spreadsheetml') => 'xls',
            str_contains($m, 'spreadsheetml'), str_contains($m, 'sheet') => 'xlsx',
            str_contains($m, 'powerpoint') && !str_contains($m, 'presentationml') => 'ppt',
            str_contains($m, 'presentationml') => 'pptx',
            str_contains($m, 'text/plain') => 'txt',
            str_contains($m, 'csv') => 'csv',
            str_contains($m, 'zip') => 'zip',
            str_contains($m, 'rar') => 'rar',
            default => 'bin',
        };
    }

    /**
     * Nome do arquivo para download (com extensão correta a partir do mime).
     */
    /**
     * Nome seguro para Content-Disposition (evita "file.enc" no navegador).
     * Sempre com extensão correta a partir do mime/tipo.
     */
    private function attachmentDownloadFilename(WhatsAppAttachment $a): string
    {
        $base = pathinfo($a->storage_path ?? '', PATHINFO_FILENAME);
        if ($base === '' || $base === false) {
            $base = ($a->type === 'image' ? 'image' : ($a->type === 'video' ? 'video' : 'document'));
        }
        $ext = $this->extensionFromMime($this->attachmentMime($a), $a->type ?? 'document');
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base);
        $safe = $safe !== '' ? $safe : 'file';
        return $safe.'.'.$ext;
    }

    /**
     * URL para exibir/baixar anexo. Sempre retorna nossa rota quando possível,
     * para imagens recebidas sem URL: ao acessar a rota, resolvemos da Evolution sob demanda.
     */
    private function attachmentUrl(WhatsAppAttachment $a): ?string
    {
        if ($a->storage_path && $a->storage_disk) {
            return route('whatsapp.inbox.attachments.show', ['attachment' => $a->public_id]);
        }
        if ($a->remote_url) {
            return $a->remote_url;
        }
        // Anexo sem arquivo ainda (ex.: imagem recebida): retorna nossa rota para resolver sob demanda
        return route('whatsapp.inbox.attachments.show', ['attachment' => $a->public_id]);
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

