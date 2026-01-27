<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppInstance extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'user_id',
        'instance_name',
        'instance_token',
        'whatsapp_number',
        'status',
        'webhook_url',
        'webhook_events',
        'webhook_base64',
        'connected_at',
        'disconnected_at',
        'metadata',
    ];

    protected $casts = [
        'instance_token' => 'encrypted',
        'webhook_events' => 'array',
        'webhook_base64' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
