<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppInboxController extends Controller
{
    private EvolutionApiHttpClient $client;

    public function __construct(EvolutionApiHttpClient $client)
    {
        $this->client = $client;
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
                'id',
                'instance_name',
                'contact_number',
                'contact_name',
                'last_message_at',
                'last_message_preview',
                'unread_count',
            ]);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function messages(WhatsAppConversation $conversation): JsonResponse
    {
        $accountId = auth()->user()->accountId();
        if ((int) $conversation->user_id !== (int) $accountId) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $afterId = (int) request()->query('after_id', 0);

        $q = WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id);

        if ($afterId > 0) {
            $q->where('id', '>', $afterId)->orderBy('id');
        } else {
            $q->orderByDesc('created_at')->limit(200);
        }

        $items = $q->get([
            'id',
            'direction',
            'message_type',
            'body',
            'status',
            'sent_at',
            'created_at',
        ]);

        if ($afterId <= 0) {
            $items = $items->reverse()->values();
        }

        // Mark as read when user opens/fetches this conversation
        if ((int) $conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }

        return response()->json([
            'success' => true,
            'items' => $items,
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

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }
}

