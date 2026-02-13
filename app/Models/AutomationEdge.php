<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationEdge extends Model
{
    protected $table = 'automation_edges';

    protected $fillable = [
        'automation_id',
        'source_node_id',
        'target_node_id',
        'source_handle',
        'target_handle',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'target_node_id');
    }
}
