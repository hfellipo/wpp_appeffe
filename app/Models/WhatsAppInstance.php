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
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'instance_token'  => 'encrypted',
        'webhook_events'  => 'array',
        'webhook_base64'  => 'boolean',
        'connected_at'    => 'datetime',
        'disconnected_at' => 'datetime',
        'last_used_at'    => 'datetime',
        'metadata'        => 'array',
    ];

    public static array $connectedStates = ['open', 'connected', 'online', 'ready'];

    /**
     * Retorna a próxima instância ativa pelo critério round-robin (menos recentemente usada).
     */
    public static function nextForUser(int $accountId): ?self
    {
        return static::query()
            ->where('user_id', $accountId)
            ->whereIn('status', static::$connectedStates)
            ->orderByRaw('last_used_at IS NULL DESC') // NULL vem primeiro (nunca usada)
            ->orderBy('last_used_at')
            ->first();
    }

    /**
     * Marca a instância como usada agora (atualiza o ponteiro do round-robin).
     */
    public function markUsed(): void
    {
        $this->timestamps = false;
        $this->last_used_at = now();
        $this->save();
        $this->timestamps = true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
