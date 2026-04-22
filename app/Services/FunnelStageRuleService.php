<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\FunnelLead;
use App\Models\FunnelStage;
use App\Models\FunnelStageRule;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class FunnelStageRuleService
{
    public function __construct(private ?WhatsAppSendService $sender = null) {}

    /**
     * When a sent message (from a funnel stage) reaches a given status, apply "message_status" rules.
     */
    public static function applyMessageStatusRules(WhatsAppMessage $message, string $newStatus): void
    {
        if ($message->direction !== 'out') return;
        if ($message->source_type !== 'funnel_stage' || ! $message->source_id) return;

        $stage = FunnelStage::query()->find($message->source_id);
        if (! $stage) return;

        $rules = FunnelStageRule::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('trigger_type', 'message_status')
            ->where('trigger_config->status', $newStatus)
            ->with('targetStage')
            ->get();

        if ($rules->isEmpty()) return;

        $contactId = static::resolveContactId($message);
        if (! $contactId) return;

        $leads = FunnelLead::query()
            ->where('funnel_stage_id', $stage->id)
            ->where('contact_id', $contactId)
            ->get();

        foreach ($leads as $lead) {
            foreach ($rules as $rule) {
                static::executeRuleAction($rule, $lead, $stage, $contactId, $message->user_id ?? null);
                break;
            }
        }
    }

    /**
     * When an incoming message arrives, apply "whatsapp_replied" rules (any reply after a funnel
     * stage message) and "specific_reply" rules (keyword match).
     *
     * "whatsapp_replied" fires whenever there is a preceding outgoing funnel_stage message in
     * the same conversation — not just quoted/cited replies — because most users just type a
     * new message without quoting.
     *
     * Pass $contactId and $accountId directly when calling from the webhook processor to avoid
     * reading from DB a conversation that hasn't been saved yet.
     */
    public static function applyReplyRules(
        WhatsAppMessage $incomingMessage,
        ?int $contactId = null,
        ?int $accountId = null,
    ): void {
        $conv = null;
        if ($contactId === null || $accountId === null) {
            $conv = $incomingMessage->conversation
                ?? $incomingMessage->conversation()->first(['id', 'contact_id', 'user_id']);
            $contactId  ??= $conv?->contact_id;
            $accountId  ??= $conv?->user_id;
        }
        $conversationContactId = $contactId;

        // --- whatsapp_replied: any incoming message when the last funnel outgoing msg exists ---
        if ($conversationContactId && $incomingMessage->conversation_id) {
            $lastFunnelMsg = WhatsAppMessage::query()
                ->where('conversation_id', $incomingMessage->conversation_id)
                ->where('direction', 'out')
                ->where('source_type', 'funnel_stage')
                ->whereNotNull('source_id')
                ->orderByDesc('id')
                ->first(['id', 'source_id', 'conversation_id']);

            if ($lastFunnelMsg && $lastFunnelMsg->source_id) {
                $stage = FunnelStage::query()->find($lastFunnelMsg->source_id);
                if ($stage) {
                    $rules = FunnelStageRule::query()
                        ->where('funnel_stage_id', $stage->id)
                        ->where('trigger_type', 'whatsapp_replied')
                        ->with('targetStage')
                        ->get();

                    if ($rules->isNotEmpty()) {
                        $leads = FunnelLead::query()
                            ->where('funnel_stage_id', $stage->id)
                            ->where('contact_id', $conversationContactId)
                            ->get();

                        foreach ($leads as $lead) {
                            foreach ($rules as $rule) {
                                static::executeRuleAction($rule, $lead, $stage, $conversationContactId, $accountId);
                                break;
                            }
                        }

                        // Mark the funnel message as "responded"
                        self::applyMessageStatusRules($lastFunnelMsg, 'responded');
                    }
                }
            }
        }

        // --- specific_reply (keyword match on any incoming message) ---
        static::applySpecificReplyRules($incomingMessage, $conversationContactId);
    }

    /**
     * Check if this incoming message matches any "specific_reply" keyword rule
     * for stages where the contact has a lead.
     */
    public static function applySpecificReplyRules(WhatsAppMessage $incomingMessage, ?int $contactId = null): void
    {
        if ($incomingMessage->direction !== 'in') return;

        $body = trim((string) ($incomingMessage->body ?? ''));
        if ($body === '') return;

        if (! $contactId) {
            $conv = $incomingMessage->conversation ?? $incomingMessage->conversation()->first(['contact_id', 'user_id']);
            $contactId = $conv->contact_id ?? null;
        }
        if (! $contactId) return;

        // Find all leads for this contact that have specific_reply rules
        $leads = FunnelLead::query()
            ->where('contact_id', $contactId)
            ->with(['stage.stageRules' => fn ($q) => $q->where('trigger_type', 'specific_reply')->with('targetStage')])
            ->get();

        $accountId = null;
        $conv = $incomingMessage->relationLoaded('conversation')
            ? $incomingMessage->conversation
            : $incomingMessage->conversation()->first(['user_id']);
        if ($conv) $accountId = $conv->user_id;

        foreach ($leads as $lead) {
            $stage = $lead->stage;
            if (! $stage) continue;

            foreach ($stage->stageRules as $rule) {
                if ($rule->trigger_type !== 'specific_reply') continue;
                $keyword = trim((string) ($rule->keyword ?? ''));
                if ($keyword === '') continue;

                if (mb_stripos($body, $keyword) !== false) {
                    static::executeRuleAction($rule, $lead, $stage, $contactId, $accountId);
                    break; // first matching rule per lead
                }
            }
        }
    }

    // -------------------------------------------------------------------------

    private static function executeRuleAction(
        FunnelStageRule $rule,
        FunnelLead      $lead,
        FunnelStage     $stage,
        int             $contactId,
        ?int            $accountId
    ): void {
        $actionType = $rule->action_type ?? 'move';

        // Move lead
        if (in_array($actionType, ['move', 'move_and_send'], true)) {
            if ($rule->targetStage && (int) $rule->target_stage_id !== (int) $stage->id) {
                $maxPos = FunnelLead::query()
                    ->where('funnel_stage_id', $rule->target_stage_id)
                    ->max('position') ?? 0;

                $lead->update([
                    'funnel_stage_id' => $rule->target_stage_id,
                    'position'        => $maxPos + 1,
                ]);

                Log::channel('single')->info('FunnelStageRuleService: lead moved', [
                    'lead_id'    => $lead->id,
                    'from_stage' => $stage->id,
                    'to_stage'   => $rule->target_stage_id,
                    'trigger'    => $rule->trigger_type,
                ]);
            }
        }

        // Send action message
        if (in_array($actionType, ['send', 'move_and_send'], true)
            && $rule->action_message
            && $accountId) {

            $contact = Contact::find($contactId);
            if ($contact) {
                try {
                    $sender = app(WhatsAppSendService::class);
                    $sender->sendTextToContact(
                        $accountId, $contact,
                        $rule->action_message,
                        null, 'funnel_stage', $stage->id
                    );
                } catch (\Throwable $e) {
                    Log::channel('single')->warning('FunnelStageRuleService: action_message send failed', [
                        'rule_id' => $rule->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private static function resolveContactId(WhatsAppMessage $message): ?int
    {
        if ($message->relationLoaded('conversation')) {
            return $message->conversation->contact_id ?? null;
        }
        $conv = $message->conversation()->first(['contact_id']);
        return $conv->contact_id ?? null;
    }
}
