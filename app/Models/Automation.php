<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_active',
        'condition_logic', // 'and' | 'or'; null = sem filtro (todos passam)
        'interval_minutes', // intervalo que o cron verifica esta automação (ex: 15 = a cada 15 min)
        'last_checked_at',
        'run_once_per_contact', // true = só uma vez por contato; false = toda vez que atender às condições
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'run_once_per_contact' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trigger(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AutomationTrigger::class)->orderBy('id');
    }

    /** Regras de condição (várias por automação). condition_logic na automation define AND/OR. Vazio = todos passam. */
    public function conditions(): HasMany
    {
        return $this->hasMany(AutomationCondition::class)->orderBy('position');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class)->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'automation_id');
    }

    public function funnelStages(): HasMany
    {
        return $this->hasMany(FunnelStage::class, 'automation_id');
    }

    public function scopeForUser($query, int $userId): mixed
    {
        return $query->where('user_id', $userId);
    }

    public static function triggerTypes(): array
    {
        return [
            'tag_added' => __('Quando o contato receber uma tag'),
            'list_added' => __('Quando o contato for adicionado a uma lista'),
        ];
    }

    public static function conditionOperators(): array
    {
        return [
            'equals' => __('é igual a'),
            'not_equals' => __('é diferente de'),
            'contains' => __('contém'),
            'is_empty' => __('está vazio'),
            'is_not_empty' => __('não está vazio'),
        ];
    }

    /** Operadores para condição "Se" baseada no status da última mensagem enviada ao contato. */
    public static function messageStatusOperators(): array
    {
        return [
            'is_sent' => __('foi enviada'),
            'is_delivered' => __('foi entregue'),
            'is_read' => __('foi lida'),
            'is_not_delivered' => __('não foi entregue'),
            'is_not_read' => __('não foi lida'),
        ];
    }

    /** Tipos de campo para condições: atributo do contato, campo personalizado ou status da mensagem. */
    public static function conditionFieldTypes(): array
    {
        return [
            'attribute' => __('Atributo do contato'),
            'custom' => __('Campo personalizado'),
            'message_status' => __('Status da última mensagem enviada'),
        ];
    }

    public static function attributeFields(): array
    {
        return [
            'name' => __('Nome'),
            'email' => __('E-mail'),
            'phone' => __('Telefone'),
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
