<?php

namespace App\Models;

use App\Models\Automation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelStage extends Model
{
    protected $fillable = ['funnel_id', 'name', 'position', 'color', 'automation_id'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(FunnelLead::class, 'funnel_stage_id')->orderBy('position');
    }
}
