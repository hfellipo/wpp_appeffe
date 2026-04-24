<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationNode extends Model
{
    protected $table = 'automation_nodes';

    protected $fillable = [
        'automation_id',
        'type',
        'position_x',
        'position_y',
        'config',
        'label',
    ];

    protected $casts = [
        'config' => 'array',
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class, 'target_node_id');
    }

    public static function nodeTypes(): array
    {
        return [
            'start'          => __('Início'),
            'send_message'   => __('Enviar mensagem'),
            'condition'      => __('Condição (Se/Senão)'),
            'delay'          => __('Aguardar (delay)'),
            'go_to'          => __('Ir para fluxo'),
            'user_input'     => __('Entrada do usuário'),
            'smart_reply'    => __('Resposta por texto'),
            'update_field'   => __('Atualizar campo'),
            'add_tag'        => __('Adicionar tag'),
            'remove_tag'     => __('Remover tag'),
            'add_list'       => __('Adicionar à lista'),
            'remove_list'    => __('Remover da lista'),
            'human_transfer' => __('Transferir para humano'),
        ];
    }
}
