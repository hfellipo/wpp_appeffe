<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgent extends Model
{
    protected $table = 'ai_agents';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'system_prompt',
        'model',
        'temperature',
        'max_tokens',
        'active',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens'  => 'integer',
        'active'      => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function resolvedModel(): string
    {
        return $this->model ?? optional(
            AiConfig::where('user_id', $this->user_id)->first()
        )->default_model ?? 'gpt-3.5-turbo';
    }

    public function resolvedTemperature(): float
    {
        return $this->temperature ?? optional(
            AiConfig::where('user_id', $this->user_id)->first()
        )->temperature ?? 0.70;
    }

    public function resolvedMaxTokens(): int
    {
        return $this->max_tokens ?? optional(
            AiConfig::where('user_id', $this->user_id)->first()
        )->max_tokens ?? 500;
    }
}
