<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelLead extends Model
{
    protected $fillable = [
        'funnel_id',
        'funnel_stage_id',
        'contact_id',
        'name',
        'title',
        'value',
        'notes',
        'due_date',
        'position',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'due_date' => 'date',
        'position' => 'integer',
    ];

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'funnel_stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
