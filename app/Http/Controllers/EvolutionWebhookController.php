<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEvolutionWebhookEvent;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class EvolutionWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Optional shared secret (if configured)
        $expected = (string) env('EVOLUTION_WEBHOOK_SECRET', '');
        if ($expected !== '') {
            $got = (string) $request->header('X-Evolution-Secret', '');
            if (!hash_equals($expected, $got)) {
                return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();

        // Evolution commonly sends { event: "...", data: {...} }
        $event = (string) ($payload['event'] ?? '');
        $data = $payload;
        if ($event !== '' && is_array($payload['data'] ?? null)) {
            $data = (array) $payload['data'];
        }

        // Fallback: infer event for legacy/single-message payloads
        if ($event === '') {
            if (Arr::has($data, 'key.remoteJid') || Arr::has($data, 'message') || Arr::has($data, 'messages')) {
                $event = 'MESSAGES_UPSERT';
            } elseif (Arr::has($data, 'state') || Arr::has($data, 'connectionState')) {
                $event = 'CONNECTION_UPDATE';
            } else {
                $event = 'UNKNOWN';
            }
        }

        // Dispatch to queue for performance (database queue is already configured)
        ProcessEvolutionWebhookEvent::dispatch($event, $data);

        return response()->json(['ok' => true]);
    }
}

