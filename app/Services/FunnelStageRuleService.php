<?php

namespace App\Services;

use App\Models\FunnelLead;
use App\Models\FunnelStage;
use App\Models\FunnelStageRule;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class FunnelStageRuleService
{
    /**
     * When a sent message (from a funnel stage) reaches a given status, apply "message_status" rules:
     * move leads in that stage to the target stage.
     */
    public static function applyMessageStatusRules(WhatsAppMessage $message, string $newStatus): void
    {
        if ($message->direction !== 'out') {
            return;
        }
        if ($message->source_type !== 'funnel_stage' || ! $message->source_id) {
            return;
        }

        $stage = FunnelStage::query()->find($message->source_id);
        if (! $stage) {
            return;
        }

        $rules = FunnelStageRule::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('trigger_type', 'message_status')
            ->where('trigger_config->status', $newStatus)
            ->with('targetStage')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $contactId = $message->conversation->contact_id ?? null;
        if (! $contactId) {
            $conv = $message->relationLoaded('conversation') ? $message->conversation : $message->conversation()->first(['contact_id']);
            $contactId = $conv->contact_id ?? null;
        }
        if (! $contactId) {
            return;
        }

        $leads = FunnelLead::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('contact_id', $contactId)
            ->get();

        foreach ($leads as $lead) {
            foreach ($rules as $rule) {
                if (! $rule->targetStage || (int) $rule->target_stage_id === (int) $stage->id) {
                    continue;
                }
                $maxPos = FunnelLead::query()
                    ->where('funnel_stage_id', $rule->target_stage_id)
                    ->max('position') ?? 0;
                $lead->update([
                    'funnel_stage_id' => $rule->target_stage_id,
                    'position' => $maxPos + 1,
                ]);
                Log::channel('single')->info('FunnelStageRuleService: lead moved by message_status rule', [
                    'lead_id' => $lead->id,
                    'from_stage' => $stage->id,
                    'to_stage' => $rule->target_stage_id,
                    'status' => $newStatus,
                ]);
                break;
            }
        }
    }

    /**
     * When an incoming message is a reply (in_reply_to_message_id set), check if the quoted message
     * was from a funnel stage and apply "whatsapp_replied" rules: move the lead to the target stage.
     * Also applies "message_status" rules with status=responded.
     */
    public static function applyReplyRules(WhatsAppMessage $incomingMessage): void
    {
        if (! $incomingMessage->in_reply_to_message_id) {
            return;
        }

        $quoted = WhatsAppMessage::query()
            ->with('conversation')
            ->find($incomingMessage->in_reply_to_message_id);

        if (! $quoted || $quoted->direction !== 'out') {
            return;
        }

        if ($quoted->source_type !== 'funnel_stage' || ! $quoted->source_id) {
            return;
        }

        $stage = FunnelStage::query()->find($quoted->source_id);
        if (! $stage) {
            return;
        }

        $rules = FunnelStageRule::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('trigger_type', 'whatsapp_replied')
            ->with('targetStage')
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $contactId = $quoted->conversation->contact_id ?? null;
        if (! $contactId) {
            return;
        }

        $leads = FunnelLead::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('contact_id', $contactId)
            ->get();

        foreach ($leads as $lead) {
            foreach ($rules as $rule) {
                if (! $rule->targetStage || (int) $rule->target_stage_id === (int) $stage->id) {
                    continue;
                }
                $maxPos = FunnelLead::query()
                    ->where('funnel_stage_id', $rule->target_stage_id)
                    ->max('position') ?? 0;
                $lead->update([
                    'funnel_stage_id' => $rule->target_stage_id,
                    'position' => $maxPos + 1,
                ]);
                Log::channel('single')->info('FunnelStageRuleService: lead moved by whatsapp_replied rule', [
                    'lead_id' => $lead->id,
                    'from_stage' => $stage->id,
                    'to_stage' => $rule->target_stage_id,
                ]);
                break; // one move per lead
            }
        }

        // Also apply message_status rules with status=responded (same event: contact replied)
        self::applyMessageStatusRules($quoted, 'responded');
    }
}
