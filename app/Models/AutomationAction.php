<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationAction extends Model
{
    protected $fillable = [
        'automation_id',
        'type',
        'config',
        'position',
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'integer',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
