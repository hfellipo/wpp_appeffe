<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEvolutionWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Optional shared secret (if configured)
        $expected = (string) env('EVOLUTION_WEBHOOK_SECRET', '');
        if ($expected !== '') {
            $got = (string) $request->header('X-Evolution-Secret', '');
            if (!hash_equals($expected, $got)) {
                Log::channel('single')->warning('Evolution webhook: secret mismatch or missing header');
                return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();

        // Log completo do que a Evolution envia (storage/logs/laravel.log)
        Log::channel('single')->info('Evolution webhook payload received', [
            'payload' => $payload,
        ]);

        // Evolution commonly sends { event: "...", instanceName: "...", data: {...} }
        $event = (string) ($payload['event'] ?? '');
        $data = $payload;
        if ($event !== '' && is_array($payload['data'] ?? null)) {
            $data = (array) $payload['data'];
            // Critical: Evolution sends instanceName at ROOT level; processor needs it inside $data
            if (isset($payload['instanceName']) && (string) $payload['instanceName'] !== '') {
                $data['instanceName'] = $payload['instanceName'];
            }
            if (isset($payload['instance']) && (string) $payload['instance'] !== '') {
                $data['instance'] = $payload['instance'];
            }
        }

        // Normalize event (Evolution may send "messages.upsert" or "MESSAGES_UPSERT")
        $event = strtoupper(str_replace(['.', '-', ' '], '_', $event));

        // Fallback: infer event for legacy/single-message payloads
        if ($event === '' || $event === 'UNKNOWN') {
            if (Arr::has($data, 'key.remoteJid') || Arr::has($data, 'message') || Arr::has($data, 'messages')) {
                $event = 'MESSAGES_UPSERT';
            } elseif (Arr::has($data, 'state') || Arr::has($data, 'connectionState')) {
                $event = 'CONNECTION_UPDATE';
            } elseif ($event === '') {
                $event = 'UNKNOWN';
            }
        }

        Log::channel('single')->info('Evolution webhook received', [
            'event' => $event,
            'instance' => $data['instanceName'] ?? $data['instance'] ?? null,
        ]);

        // Dispatch to queue for performance (database queue is already configured)
        ProcessEvolutionWebhookEvent::dispatch($event, $data);

        return response()->json(['ok' => true]);
    }
}

