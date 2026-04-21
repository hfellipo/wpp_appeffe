<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'public_id',
        'conversation_id',
        'automation_run_id',
        'source_type', // automation_run|funnel_stage
        'source_id',
        'direction', // in|out
        'participant_jid', // in groups: who sent (e.g. 5511999@s.whatsapp.net)
        'sender_name', // in groups: display name of sender
        'message_type', // text|image|video|document|audio|unknown
        'body',
        'remote_id',
        'in_reply_to_message_id', // when direction=in: message being replied to (so we know contact "responded" to automation/funnel)
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'raw_payload',
        'reactions',
    ];

    protected $casts = [
        'body' => 'encrypted',
        'reactions' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'raw_payload' => 'encrypted:array',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function automationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'in_reply_to_message_id');
    }

    /** Incoming messages that replied to this (outgoing) message. */
    public function replies(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'in_reply_to_message_id');
    }

    /** Status for funnel display: responded | read | delivered | sent | failed */
    public function funnelDisplayStatus(): string
    {
        $hasReplies = $this->relationLoaded('replies')
            ? $this->replies->isNotEmpty()
            : $this->replies()->exists();
        if ($hasReplies) {
            return 'responded';
        }
        if ($this->read_at) {
            return 'read';
        }
        if ($this->delivered_at) {
            return 'delivered';
        }
        if ($this->status === 'sent' || $this->sent_at) {
            return 'sent';
        }
        return 'failed';
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WhatsAppAttachment::class, 'message_id');
    }
}

