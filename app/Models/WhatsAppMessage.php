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
        'direction', // in|out
        'participant_jid', // in groups: who sent (e.g. 5511999@s.whatsapp.net)
        'sender_name', // in groups: display name of sender
        'message_type', // text|image|video|document|audio|unknown
        'body',
        'remote_id',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'raw_payload',
    ];

    protected $casts = [
        'body' => 'encrypted',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(WhatsAppAttachment::class, 'message_id');
    }
}

