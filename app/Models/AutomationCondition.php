<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationCondition extends Model
{
    protected $fillable = [
        'automation_id',
        'type',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
