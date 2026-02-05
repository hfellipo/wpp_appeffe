<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    use HasFactory;

    protected $table = 'automation_runs';

    protected $fillable = [
        'contact_id',
        'automation_id',
        'ran_at',
        'status',
        'metadata',
        'resume_at',
        'resume_from_position',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'resume_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'automation_run_id');
    }
}
