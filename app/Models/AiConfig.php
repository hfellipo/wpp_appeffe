<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class AiConfig extends Model
{
    protected $table = 'ai_configs';

    protected $fillable = [
        'user_id',
        'openai_api_key',
        'default_model',
        'temperature',
        'max_tokens',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens'  => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setOpenaiApiKeyAttribute(?string $value): void
    {
        $this->attributes['openai_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getOpenaiApiKeyAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function availableModels(): array
    {
        return [
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Padrão)',
            'gpt-4o'        => 'GPT-4o (Pro)',
            'gpt-4-turbo'   => 'GPT-4 Turbo',
            'gpt-4o-mini'   => 'GPT-4o Mini',
        ];
    }
}
