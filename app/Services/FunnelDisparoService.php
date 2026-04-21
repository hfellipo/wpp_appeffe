<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\FunnelDisparo;
use App\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FunnelDisparoService
{
    public function __construct(private WhatsAppSendService $sender) {}

    /**
     * Called by the cron command (automations:run) to process all pending/running disparos.
     */
    public function processPending(): void
    {
        $disparos = FunnelDisparo::query()
            ->whereIn('status', ['pending', 'running'])
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('id')
            ->get();

        foreach ($disparos as $disparo) {
            try {
                $this->processNext($disparo);
            } catch (\Throwable $e) {
                Log::channel('single')->error('FunnelDisparoService error', [
                    'disparo_id' => $disparo->id,
                    'error'      => $e->getMessage(),
                ]);
                $disparo->update(['status' => 'failed']);
            }
        }
    }

    /**
     * Send the next message(s) for a disparo.
     * - delay_seconds = 0 → sends ALL contacts immediately in a loop
     * - delay_seconds > 0 → sends ONE per cron tick (respects delay between calls)
     */
    public function processNext(FunnelDisparo $disparo): void
    {
        if (! $disparo->readyToSendNext()) {
            return;
        }

        if ($disparo->delay_seconds === 0) {
            $this->processAll($disparo);
        } else {
            $this->sendOne($disparo);
        }
    }

    /**
     * Send all remaining contacts immediately (no delay).
     */
    private function processAll(FunnelDisparo $disparo): void
    {
        $disparo->status = 'running';
        $disparo->save();

        $ids = $disparo->contact_ids ?? [];

        foreach ($ids as $contactId) {
            // Re-read from DB in case it was cancelled externally
            $disparo->refresh();
            if ($disparo->status === 'cancelled') return;

            $this->sendOne($disparo, (int) $contactId);
        }
    }

    /**
     * Send a single contact from the disparo queue.
     * If $contactId is null, picks the next pending one.
     */
    private function sendOne(FunnelDisparo $disparo, ?int $contactId = null): void
    {
        $disparo->status = 'running';

        if ($contactId === null) {
            $contactId = $disparo->nextContactId();
        }

        if ($contactId === null) {
            $disparo->status       = 'completed';
            $disparo->completed_at = now();
            $disparo->save();
            return;
        }

        $contact = Contact::find($contactId);
        $sent    = false;

        if ($contact) {
            try {
                if ($disparo->mode === 'random') {
                    $this->pinRandomInstance($disparo->user_id);
                }

                if ($disparo->image_path && Storage::disk('local')->exists($disparo->image_path)) {
                    $this->sender->sendMediaToContact(
                        $disparo->user_id, $contact,
                        $disparo->image_path, $disparo->image_mime ?? 'image/jpeg',
                        $disparo->message ?? '',
                        null, 'funnel_stage', $disparo->funnel_stage_id
                    );
                } else {
                    $this->sender->sendTextToContact(
                        $disparo->user_id, $contact,
                        $disparo->message ?? '',
                        null, 'funnel_stage', $disparo->funnel_stage_id
                    );
                }
                $sent = true;
            } catch (\Throwable $e) {
                Log::channel('single')->warning('FunnelDisparoService: send failed', [
                    'disparo_id' => $disparo->id,
                    'contact_id' => $contactId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($sent) {
            $disparo->sent_count++;
        } else {
            $disparo->failed_count++;
        }
        $disparo->last_sent_at = now();

        $processed = $disparo->sent_count + $disparo->failed_count;
        if ($processed >= $disparo->total_contacts) {
            $disparo->status       = 'completed';
            $disparo->completed_at = now();
        }

        $disparo->save();
    }

    /**
     * Create a new disparo from the stage send-message form.
     *
     * @param array{
     *   message?: string|null,
     *   image_path?: string|null,
     *   image_mime?: string|null,
     *   mode: string,
     *   delay_seconds: int,
     *   scheduled_at?: \Carbon\Carbon|null,
     * } $options
     */
    public function createFromStage(
        int $accountId,
        int $stageId,
        array $contactIds,
        array $options
    ): FunnelDisparo {
        $orderedIds = $contactIds;

        if (($options['mode'] ?? 'sequential') === 'random') {
            shuffle($orderedIds);
        }

        return FunnelDisparo::create([
            'user_id'         => $accountId,
            'funnel_stage_id' => $stageId,
            'status'          => 'pending',
            'message'         => $options['message'] ?? null,
            'image_path'      => $options['image_path'] ?? null,
            'image_mime'      => $options['image_mime'] ?? null,
            'mode'            => $options['mode'] ?? 'sequential',
            'delay_seconds'   => (int) ($options['delay_seconds'] ?? 0),
            'contact_ids'     => $orderedIds,
            'total_contacts'  => count($orderedIds),
            'scheduled_at'    => $options['scheduled_at'] ?? null,
        ]);
    }

    /**
     * For "random" mode: temporarily override instance selection by touching last_used_at on a random active instance.
     */
    private function pinRandomInstance(int $accountId): void
    {
        $instances = WhatsAppInstance::query()
            ->where('user_id', $accountId)
            ->whereIn('status', WhatsAppInstance::$connectedStates)
            ->get();

        if ($instances->count() <= 1) return;

        $pick = $instances->random();
        // Set last_used_at far in the past so nextForUser will NOT pick it next — forcing rotation
        // Actually for "random", we want random selection, so we randomize last_used_at
        $pick->timestamps = false;
        $pick->last_used_at = now()->subSeconds(rand(1, 3600));
        $pick->save();
        $pick->timestamps = true;
    }
}
