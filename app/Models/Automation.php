<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Automation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trigger(): HasOne
    {
        return $this->hasOne(AutomationTrigger::class)->orderBy('id');
    }

    public function condition(): HasOne
    {
        return $this->hasOne(AutomationCondition::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class)->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'automation_id');
    }

    public function scopeForUser($query, int $userId): mixed
    {
        return $query->where('user_id', $userId);
    }

    public static function triggerTypes(): array
    {
        return [
            'list_added' => __('Adicionado a uma lista'),
            'tag_added' => __('Recebeu uma tag'),
            'manual' => __('Manual (para teste)'),
            'schedule_daily' => __('Recorrência: todo dia'),
            'schedule_weekly' => __('Recorrência: toda semana'),
            'schedule_monthly' => __('Recorrência: todo mês'),
            'schedule_yearly' => __('Recorrência: todo ano'),
        ];
    }

    public static function conditionTypes(): array
    {
        return [
            'always_yes' => __('Sempre (sim)'),
            'always_no' => __('Nunca (não)'),
            'contact_in_list' => __('Contato está na lista'),
            'contact_has_tag' => __('Contato tem a tag'),
        ];
    }

    public static function actionTypes(): array
    {
        return [
            'send_whatsapp_message' => __('Enviar mensagem WhatsApp'),
            'add_to_list' => __('Adicionar a uma lista'),
            'add_tag' => __('Adicionar tag'),
            'wait_delay' => __('Aguardar (delay)'),
        ];
    }
}
