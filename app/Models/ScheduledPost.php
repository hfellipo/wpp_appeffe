<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPost extends Model
{
    protected $fillable = [
        'user_id',
        'scheduled_at',
        'target_type',
        'target_id',
        'message',
        'image_path',
        'image_mime',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /** Posts ainda não enviados e cuja data/hora já passou (usa o fuso do app). */
    public function scopePending($query)
    {
        $now = Carbon::now(config('app.timezone'));
        return $query->whereNull('sent_at')->where('scheduled_at', '<=', $now);
    }

    public static function targetTypes(): array
    {
        return [
            'group' => __('Grupo (WhatsApp)'),
            'list' => __('Lista de contatos'),
            'tag' => __('Quem tem a tag'),
            'funnel_stage' => __('Coluna do funil'),
        ];
    }

    public function funnelStage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\FunnelStage::class, 'target_id');
    }
}
