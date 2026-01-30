<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Best-effort normalization for instance name
        $instance = (string) ($payload['instanceName']
            ?? $payload['instance']
            ?? $payload['numberId']
            ?? ($payload['instance']['instanceName'] ?? '')
        );
        $instance = preg_replace('/\D/', '', $instance);

        if ($instance === '') {
            return response()->json(['ok' => false, 'error' => 'Missing instance'], 422);
        }

        $wa = WhatsAppInstance::query()
            ->where('instance_name', $instance)
            ->first();

        if (!$wa) {
            // If the instance is not registered locally, accept but do nothing
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        // Update instance status if present
        $maybeState = (string) ($payload['state'] ?? ($payload['connectionState'] ?? ($payload['status'] ?? '')));
        if ($maybeState !== '') {
            $wa->status = $maybeState;
            $wa->save();
        }

        // The "Evolution Channel Webhook" sample uses:
        // key.remoteJid, key.fromMe, key.id, pushName, message.{conversation|...}, messageType
        $remoteJid = (string) ($payload['key']['remoteJid'] ?? ($payload['remoteJid'] ?? ''));
        $fromMe = (bool) ($payload['key']['fromMe'] ?? false);
        $remoteId = (string) ($payload['key']['id'] ?? ($payload['id'] ?? ''));
        $pushName = (string) ($payload['pushName'] ?? '');
        $messageType = (string) ($payload['messageType'] ?? 'unknown');

        $contactNumber = preg_replace('/\D/', '', $remoteJid);
        if ($contactNumber === '') {
            // Nothing to store (non-message event)
            return response()->json(['ok' => true]);
        }

        $accountId = (int) $wa->user_id;

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            [
                'user_id' => $accountId,
                'instance_name' => $instance,
                'contact_number' => $contactNumber,
            ],
            [
                'contact_name' => $pushName !== '' ? $pushName : null,
                'unread_count' => 0,
            ]
        );

        if ($pushName !== '' && !$conversation->contact_name) {
            $conversation->contact_name = $pushName;
        }

        // Extract text / caption best-effort
        $body = null;
        $msgNode = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        if (isset($msgNode['conversation']) && is_string($msgNode['conversation'])) {
            $body = $msgNode['conversation'];
        } elseif (isset($msgNode['extendedTextMessage']['text']) && is_string($msgNode['extendedTextMessage']['text'])) {
            $body = $msgNode['extendedTextMessage']['text'];
            $messageType = $messageType === 'unknown' ? 'extendedTextMessage' : $messageType;
        } else {
            // Try common media captions
            foreach (['imageMessage', 'videoMessage', 'documentMessage'] as $k) {
                if (isset($msgNode[$k]['caption']) && is_string($msgNode[$k]['caption']) && $msgNode[$k]['caption'] !== '') {
                    $body = $msgNode[$k]['caption'];
                    $messageType = $k;
                    break;
                }
            }
        }

        $direction = $fromMe ? 'out' : 'in';

        // Prevent duplicates by remote_id when available
        if ($remoteId !== '') {
            $exists = WhatsAppMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('remote_id', $remoteId)
                ->exists();
            if ($exists) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }
        }

        $msg = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => $direction,
            'message_type' => $this->mapType($messageType),
            'body' => $body,
            'remote_id' => $remoteId !== '' ? $remoteId : null,
            'status' => null,
            'sent_at' => now(),
            'raw_payload' => $payload,
        ]);

        $conversation->last_message_at = $msg->sent_at ?? $msg->created_at;
        $conversation->last_message_preview = $body ? mb_substr($body, 0, 500) : '[' . $msg->message_type . ']';
        if (!$fromMe) {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
        }
        $conversation->save();

        return response()->json(['ok' => true]);
    }

    private function mapType(string $t): string
    {
        $t = strtolower(trim($t));
        if ($t === '') return 'unknown';
        if (str_contains($t, 'image')) return 'image';
        if (str_contains($t, 'video')) return 'video';
        if (str_contains($t, 'audio')) return 'audio';
        if (str_contains($t, 'document')) return 'document';
        if (str_contains($t, 'conversation') || str_contains($t, 'text')) return 'text';
        return 'unknown';
    }
}

