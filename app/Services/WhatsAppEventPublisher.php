<?php

namespace App\Services;

use App\Models\WhatsAppEvent;

class WhatsAppEventPublisher
{
    /**
     * Persist a realtime event to be consumed by /whatsapp/stream (SSE).
     *
     * The payload is stored encrypted at rest (see WhatsAppEvent casts).
     *
     * @param  int  $accountId  account owner id (same semantics: user->accountId())
     * @param  string  $type    e.g. wa.message.created, wa.conversation.read
     * @param  array<string,mixed>  $payload
     */
    public function publish(int $accountId, string $type, array $payload = []): void
    {
        $type = trim($type);
        if ($accountId <= 0 || $type === '') {
            return;
        }

        try {
            WhatsAppEvent::create([
                'user_id' => $accountId,
                'type' => $type,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            // Never let realtime publishing break the main flow (webhook/UX).
            // Best-effort only.
        }
    }
}

