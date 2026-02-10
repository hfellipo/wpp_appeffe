<?php

namespace App\Models;

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

    public function scopePending($query)
    {
        return $query->whereNull('sent_at')->where('scheduled_at', '<=', now());
    }

    public static function targetTypes(): array
    {
        return [
            'group' => __('Grupo (WhatsApp)'),
            'list' => __('Lista de contatos'),
            'tag' => __('Quem tem a tag'),
        ];
    }
}
