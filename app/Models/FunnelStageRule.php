<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelStageRule extends Model
{
    protected $fillable = [
        'funnel_stage_id',
        'trigger_type',
        'trigger_config',
        'target_stage_id',
    ];

    protected $casts = [
        'trigger_config' => 'array',
    ];

    public static function triggerTypes(): array
    {
        return [
            'message_status' => __('Status da mensagem enviada'),
            'whatsapp_replied' => __('Resposta após mensagem do funil'),
            'tag_added' => __('Recebeu a tag'),
            'list_added' => __('Foi adicionado à lista'),
        ];
    }

    /** Statuses that can trigger a stage change (same as funnel display + responded). */
    public static function messageStatusOptions(): array
    {
        return [
            'sent' => __('Enviada'),
            'delivered' => __('Entregue'),
            'read' => __('Lida'),
            'failed' => __('Falhou'),
            'responded' => __('Respondida'),
        ];
    }

    public function sourceStage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'funnel_stage_id');
    }

    public function targetStage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'target_stage_id');
    }
}
