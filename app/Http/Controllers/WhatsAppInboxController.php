<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiHttpClient;
use App\Services\WhatsAppEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $number = preg_replace('/\D/', '', (string) $conversation->contact_number);
        if ($number === '') {
            return response()->json(['success' => false, 'error' => 'Contato inválido.'], 422);
        }

        $text = (string) $request->input('text');

        // Evolution API (v2.3+): POST /message/sendText/{instance}
        $resp = $this->client->post("/message/sendText/{$instance}", [
            'number' => $number,
            'text' => $text,
        ]);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            return response()->json([
                'success' => false,
                'http_status' => $resp['status'],
                'error' => 'Falha ao enviar mensagem na Evolution.',
                'details' => $resp['json'] ?? $resp['text'],
            ], 502);
        }

        $msg = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'message_type' => 'text',
            'body' => $text,
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
                'created_at' => optional($msg->created_at)->toIso8601String(),
            ],
        ]);
    }
}

