<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'contact_field_id',
        'value',
    ];

    /**
     * Get the contact that owns the value.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the field definition.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id');
    }

    /**
     * Get the formatted value based on field type.
     */
    public function getFormattedValueAttribute(): ?string
    {
        if (is_null($this->value)) {
            return null;
        }

        return match ($this->field?->type) {
            'date' => $this->value ? date('d/m/Y', strtotime($this->value)) : null,
            'number' => number_format((float) $this->value, 2, ',', '.'),
            'url' => $this->value,
            default => $this->value,
        };
    }
}
