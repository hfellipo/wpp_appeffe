<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Funnel extends Model
{
    protected $fillable = ['user_id', 'name', 'token'];

    protected static function booted(): void
    {
        static::creating(function (Funnel $funnel) {
            if (empty($funnel->token)) {
                $funnel->token = Str::random(32);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FunnelStage::class)->orderBy('position');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(FunnelLead::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function defaultStages(): array
    {
        return [
            ['name' => 'Leads de entrada', 'position' => 0, 'color' => 'yellow'],
            ['name' => 'Decidindo', 'position' => 1, 'color' => 'purple'],
            ['name' => 'Discussão de contrato', 'position' => 2, 'color' => 'green'],
            ['name' => 'Decisão final', 'position' => 3, 'color' => 'blue'],
        ];
    }
}
