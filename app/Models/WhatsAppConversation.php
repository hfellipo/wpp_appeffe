<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'public_id',
        'user_id',
        'instance_name',
        'kind',
        'peer_jid',
        'contact_number',
        'contact_name',
        'last_message_at',
        'last_message_preview',
        'last_message_sender', // for groups: name of sender of last message
        'unread_count',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
        'metadata' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

