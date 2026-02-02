<?php

namespace App\Services;

use App\Models\WhatsAppAttachment;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppGroup;
use App\Models\WhatsAppInstance;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class EvolutionWebhookProcessor
{
    /**
     * Best-effort realtime publishing to the /whatsapp UI (SSE).
     *
     * @param  array<string,mixed>  $payload
     */
    private function publish(int $accountId, string $type, array $payload = []): void
    {
        app(WhatsAppEventPublisher::class)->publish($accountId, $type, $payload);
    }

    /**
     * Main entry point for a single webhook event.
     *
     * @param  string  $event  e.g. MESSAGES_UPSERT, CONNECTION_UPDATE...
     * @param  array   $data   event payload (already unwrapped from {event,data} if applicable)
     */
    public function handle(string $event, array $data): void
    {
        $event = strtoupper(trim($event));

        // Instance name (digits) used as key in our system
        $instance = $this->normalizeInstance(
            (string) (Arr::get($data, 'instanceName')
                ?? Arr::get($data, 'instance')
                ?? Arr::get($data, 'numberId')
                ?? Arr::get($data, 'instance.instanceName')
                ?? '')
        );
        if ($instance === '') {
            return;
        }

        $wa = WhatsAppInstance::query()->where('instance_name', $instance)->first();
        if (!$wa) {
            return;
        }

        // Connection/presence events can be processed without message parsing
        if (str_contains($event, 'CONNECTION')) {
            $this->handleConnectionUpdate($wa, $data);
            return;
        }
        if (str_contains($event, 'PRESENCE')) {
            $this->handlePresenceUpdate($wa, $data);
            return;
        }

        if (str_contains($event, 'CONTACT')) {
            $this->handleContacts($wa, $data);
            return;
        }
        if (str_contains($event, 'GROUP')) {
            $this->handleGroups($wa, $data);
            return;
        }
        if (str_contains($event, 'CHAT')) {
            // Best-effort: keep for later expansion
            $this->handleChats($wa, $data);
            return;
        }

        if (str_contains($event, 'MESSAGES')) {
            if (str_contains($event, 'DELETE')) {
                $this->handleMessagesDelete($wa, $data);
                return;
            }
            if (str_contains($event, 'UPDATE')) {
                $this->handleMessagesUpdate($wa, $data);
                return;
            }
            // default to UPSERT
            $this->handleMessagesUpsert($wa, $data);
            return;
        }

        // Unknown/unhandled event => ignore
    }

    private function handleConnectionUpdate(WhatsAppInstance $wa, array $data): void
    {
        $state = (string) (Arr::get($data, 'state') ?? Arr::get($data, 'connectionState') ?? Arr::get($data, 'status') ?? '');
        if ($state !== '') {
            $wa->status = $state;
            $wa->save();

            $this->publish((int) $wa->user_id, 'wa.connection.update', [
                'instance_name' => $wa->instance_name,
                'state' => $wa->status,
                'updated_at' => optional($wa->updated_at)->toIso8601String(),
            ]);
        }
    }

    private function handlePresenceUpdate(WhatsAppInstance $wa, array $data): void
    {
        // Store best-effort presence with TTL (cache store can be database)
        $remoteJid = (string) Arr::get($data, 'key.remoteJid', Arr::get($data, 'remoteJid', ''));
        $remoteJid = trim($remoteJid);
        if ($remoteJid === '') return;

        $presence = Arr::get($data, 'presence', Arr::get($data, 'data.presence', null));
        $key = "wa:presence:{$wa->instance_name}:{$remoteJid}";
        Cache::put($key, $presence, now()->addSeconds(25));
    }

    private function handleContacts(WhatsAppInstance $wa, array $data): void
    {
        $accountId = (int) $wa->user_id;

        // Evolution may deliver a batch under data.contacts or contacts
        $contacts = Arr::get($data, 'contacts') ?? Arr::get($data, 'data.contacts') ?? Arr::get($data, 'data', []);
        if (!is_array($contacts)) return;

        foreach ($contacts as $c) {
            if (!is_array($c)) continue;
            $jid = (string) ($c['id'] ?? $c['jid'] ?? $c['remoteJid'] ?? '');
            $number = $this->normalizeNumber($jid);
            if ($number === '') continue;

            WhatsAppContact::query()->updateOrCreate(
                [
                    'user_id' => $accountId,
                    'instance_name' => $wa->instance_name,
                    'contact_number' => $number,
                ],
                [
                    'contact_jid' => $jid ?: "{$number}@s.whatsapp.net",
                    'display_name' => (string) ($c['name'] ?? $c['pushName'] ?? $c['notify'] ?? ''),
                    'avatar_url' => (string) ($c['profilePicUrl'] ?? $c['avatar'] ?? ''),
                    'metadata' => $c,
                ]
            );
        }
    }

    private function handleGroups(WhatsAppInstance $wa, array $data): void
    {
        $accountId = (int) $wa->user_id;

        $groups = Arr::get($data, 'groups') ?? Arr::get($data, 'data.groups') ?? Arr::get($data, 'data', []);
        if (!is_array($groups)) return;

        foreach ($groups as $g) {
            if (!is_array($g)) continue;
            $jid = (string) ($g['id'] ?? $g['jid'] ?? $g['remoteJid'] ?? $g['groupJid'] ?? '');
            if ($jid === '') continue;

            WhatsAppGroup::query()->updateOrCreate(
                [
                    'user_id' => $accountId,
                    'instance_name' => $wa->instance_name,
                    'group_jid' => $jid,
                ],
                [
                    'subject' => (string) ($g['subject'] ?? $g['name'] ?? ''),
                    'description' => (string) ($g['desc'] ?? $g['description'] ?? ''),
                    'metadata' => $g,
                ]
            );
        }
    }

    private function handleChats(WhatsAppInstance $wa, array $data): void
    {
        // Placeholder: some installations send chats list updates.
        // We currently build conversations from message events.
        // Keep data in instance metadata for diagnostics (encrypted cast on instance token already).
        $meta = is_array($wa->metadata) ? $wa->metadata : [];
        $meta['last_chat_event'] = [
            'at' => now()->toIso8601String(),
            'sample' => array_slice($data, 0, 50, true),
        ];
        $wa->metadata = $meta;
        $wa->save();
    }

    private function handleMessagesUpsert(WhatsAppInstance $wa, array $data): void
    {
        $accountId = (int) $wa->user_id;

        // Support 2 shapes:
        // A) Single message payload (Evolution Channel Webhook): key.remoteJid, key.id...
        // B) Batch payload: messages: [ { key, message, ... } ]
        $messages = [];
        if (is_array(Arr::get($data, 'messages'))) {
            $messages = Arr::get($data, 'messages');
        } elseif (is_array(Arr::get($data, 'data.messages'))) {
            $messages = Arr::get($data, 'data.messages');
        } else {
            $messages = [$data];
        }

        foreach ($messages as $m) {
            if (!is_array($m)) continue;

            $remoteJid = (string) Arr::get($m, 'key.remoteJid', Arr::get($m, 'remoteJid', ''));
            $remoteJid = trim($remoteJid);
            if ($remoteJid === '') continue;

            $fromMe = (bool) Arr::get($m, 'key.fromMe', false);
            $remoteId = (string) Arr::get($m, 'key.id', Arr::get($m, 'id', ''));
            $pushName = (string) Arr::get($m, 'pushName', Arr::get($m, 'data.pushName', ''));
            $messageTypeRaw = (string) Arr::get($m, 'messageType', 'unknown');

            $kind = str_contains($remoteJid, '@g.us') ? 'group' : 'direct';

            $conversation = WhatsAppConversation::query()->firstOrCreate(
                [
                    'user_id' => $accountId,
                    'instance_name' => $wa->instance_name,
                    'peer_jid' => $remoteJid,
                ],
                [
                    'kind' => $kind,
                    'contact_number' => $this->normalizeNumber($remoteJid),
                    'contact_name' => $pushName !== '' ? $pushName : null,
                    'unread_count' => 0,
                ]
            );

            if ($pushName !== '' && !$conversation->contact_name) {
                $conversation->contact_name = $pushName;
            }
            if (!$conversation->kind) {
                $conversation->kind = $kind;
            }

            // Dedupe by remote_id
            if ($remoteId !== '') {
                $exists = WhatsAppMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('remote_id', $remoteId)
                    ->exists();
                if ($exists) {
                    continue;
                }
            }

            [$body, $mappedType, $attachment] = $this->extractBodyAndAttachment($m, $messageTypeRaw);

            $msg = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => $fromMe ? 'out' : 'in',
                'message_type' => $mappedType,
                'body' => $body,
                'remote_id' => $remoteId !== '' ? $remoteId : null,
                'status' => null,
                'sent_at' => now(),
                'raw_payload' => $m,
            ]);

            // Attachment (best-effort)
            if ($attachment) {
                WhatsAppAttachment::create([
                    'message_id' => $msg->id,
                    'type' => $attachment['type'] ?? null,
                    'mime' => $attachment['mime'] ?? null,
                    'size' => $attachment['size'] ?? null,
                    'remote_url' => $attachment['remote_url'] ?? null,
                    'caption_preview' => $attachment['caption_preview'] ?? null,
                    'raw_payload' => $attachment['raw_payload'] ?? null,
                ]);
            }

            $conversation->last_message_at = $msg->sent_at ?? $msg->created_at;
            $conversation->last_message_preview = $body ? mb_substr($body, 0, 500) : '[' . $mappedType . ']';
            if (!$fromMe) {
                $conversation->unread_count = (int) $conversation->unread_count + 1;
            }
            $conversation->save();

            $avatarUrl = null;
            $displayName = null;
            if ($kind === 'direct') {
                $num = $this->normalizeNumber($remoteJid);
                if ($num !== '') {
                    $ct = WhatsAppContact::query()
                        ->where('user_id', $accountId)
                        ->where('instance_name', $wa->instance_name)
                        ->where('contact_number', $num)
                        ->first(['avatar_url', 'display_name']);
                    if ($ct) {
                        $avatarUrl = $ct->avatar_url ?: null;
                        $displayName = $ct->display_name ?: null;
                    }
                }
            }

            // Notify UI in realtime
            $this->publish($accountId, 'wa.message.created', [
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

            // Also upsert contact record for direct chats (best-effort)
            if ($kind === 'direct') {
                $num = $this->normalizeNumber($remoteJid);
                if ($num !== '') {
                    WhatsAppContact::query()->updateOrCreate(
                        [
                            'user_id' => $accountId,
                            'instance_name' => $wa->instance_name,
                            'contact_number' => $num,
                        ],
                        [
                            'contact_jid' => $remoteJid,
                            'display_name' => $pushName !== '' ? $pushName : null,
                        ]
                    );
                }
            }
        }
    }

    private function handleMessagesUpdate(WhatsAppInstance $wa, array $data): void
    {
        // Best-effort: update message status using remote id
        $remoteJid = (string) Arr::get($data, 'key.remoteJid', Arr::get($data, 'remoteJid', ''));
        $remoteId = (string) Arr::get($data, 'key.id', Arr::get($data, 'id', ''));
        $status = (string) (Arr::get($data, 'status') ?? Arr::get($data, 'state') ?? '');

        if ($remoteId === '') return;

        $q = WhatsAppMessage::query()->where('remote_id', $remoteId);
        if ($remoteJid !== '') {
            $conv = WhatsAppConversation::query()
                ->where('user_id', (int) $wa->user_id)
                ->where('instance_name', $wa->instance_name)
                ->where('peer_jid', $remoteJid)
                ->first();
            if ($conv) {
                $q->where('conversation_id', $conv->id);
            }
        }

        $msg = $q->latest('id')->first();
        if (!$msg) return;

        if ($status !== '') {
            $msg->status = $status;
            $s = strtolower($status);
            if ($s === 'delivered' && !$msg->delivered_at) $msg->delivered_at = now();
            if (in_array($s, ['read', 'seen'], true) && !$msg->read_at) $msg->read_at = now();
            $msg->save();

            $conv = WhatsAppConversation::query()->where('id', $msg->conversation_id)->first(['public_id']);
            $this->publish((int) $wa->user_id, 'wa.message.updated', [
                'conversation_id' => $conv?->public_id,
                'message' => [
                    'id' => $msg->public_id,
                    'status' => $msg->status,
                    'delivered_at' => optional($msg->delivered_at)->toIso8601String(),
                    'read_at' => optional($msg->read_at)->toIso8601String(),
                ],
            ]);
        }
    }

    private function handleMessagesDelete(WhatsAppInstance $wa, array $data): void
    {
        $remoteId = (string) Arr::get($data, 'key.id', Arr::get($data, 'id', ''));
        if ($remoteId === '') return;

        $msg = WhatsAppMessage::query()->where('remote_id', $remoteId)->latest('id')->first();
        if (!$msg) return;

        $msg->message_type = 'deleted';
        $msg->body = null;
        $msg->save();

        $conv = WhatsAppConversation::query()->where('id', $msg->conversation_id)->first(['public_id']);
        $this->publish((int) $wa->user_id, 'wa.message.deleted', [
            'conversation_id' => $conv?->public_id,
            'message' => [
                'id' => $msg->public_id,
            ],
        ]);
    }

    /**
     * @return array{0:?string,1:string,2:?array}
     */
    private function extractBodyAndAttachment(array $payload, string $messageTypeRaw): array
    {
        $msgNode = is_array($payload['message'] ?? null) ? $payload['message'] : [];

        $body = null;
        $attachment = null;

        // Text
        if (isset($msgNode['conversation']) && is_string($msgNode['conversation'])) {
            $body = $msgNode['conversation'];
            return [$body, 'text', null];
        }
        if (isset($msgNode['extendedTextMessage']['text']) && is_string($msgNode['extendedTextMessage']['text'])) {
            $body = $msgNode['extendedTextMessage']['text'];
            return [$body, 'text', null];
        }

        // Media captions (best-effort)
        foreach (['imageMessage' => 'image', 'videoMessage' => 'video', 'documentMessage' => 'document', 'audioMessage' => 'audio'] as $k => $t) {
            if (isset($msgNode[$k]) && is_array($msgNode[$k])) {
                $caption = $msgNode[$k]['caption'] ?? null;
                if (is_string($caption) && $caption !== '') {
                    $body = $caption;
                }
                $mediaUrl = $msgNode['mediaUrl'] ?? $payload['mediaUrl'] ?? null;
                $attachment = [
                    'type' => $t,
                    'mime' => is_string($msgNode[$k]['mimetype'] ?? null) ? $msgNode[$k]['mimetype'] : null,
                    'size' => is_numeric($msgNode[$k]['fileLength'] ?? null) ? (int) $msgNode[$k]['fileLength'] : null,
                    'remote_url' => is_string($mediaUrl) ? $mediaUrl : null,
                    'caption_preview' => $body ? mb_substr($body, 0, 500) : null,
                    'raw_payload' => $msgNode[$k],
                ];
                return [$body, $t, $attachment];
            }
        }

        // Fallback
        $mapped = $this->mapType($messageTypeRaw);
        return [$body, $mapped, null];
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
        if (str_contains($t, 'sticker')) return 'sticker';
        return 'unknown';
    }

    private function normalizeInstance(string $s): string
    {
        return preg_replace('/\\D/', '', $s) ?: '';
    }

    private function normalizeNumber(string $jidOrNumber): string
    {
        return preg_replace('/\\D/', '', (string) $jidOrNumber) ?: '';
    }
}

