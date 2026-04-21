<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelDisparo extends Model
{
    protected $table = 'funnel_disparos';

    protected $fillable = [
        'user_id', 'funnel_stage_id', 'status',
        'message', 'image_path', 'image_mime',
        'mode', 'delay_seconds',
        'contact_ids', 'total_contacts', 'sent_count', 'failed_count',
        'last_sent_at', 'scheduled_at', 'completed_at',
    ];

    protected $casts = [
        'contact_ids'  => 'array',
        'last_sent_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'funnel_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): int
    {
        if ($this->total_contacts <= 0) return 0;
        return (int) round(($this->sent_count + $this->failed_count) / $this->total_contacts * 100);
    }

    /** Returns true if this disparo is ready to process the next contact. */
    public function readyToSendNext(): bool
    {
        if ($this->status !== 'running' && $this->status !== 'pending') return false;
        if ($this->scheduled_at && $this->scheduled_at->isFuture()) return false;
        if ($this->delay_seconds > 0 && $this->last_sent_at) {
            return $this->last_sent_at->addSeconds($this->delay_seconds)->isPast();
        }
        return true;
    }

    /** Next contact ID to send to. */
    public function nextContactId(): ?int
    {
        $ids = $this->contact_ids ?? [];
        $processed = $this->sent_count + $this->failed_count;
        return $ids[$processed] ?? null;
    }
}
