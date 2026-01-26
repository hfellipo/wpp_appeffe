<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'notes',
    ];

    /**
     * Get the user that owns the contact.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the custom field values for the contact.
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    /**
     * Get the value of a specific custom field.
     */
    public function getFieldValue(int|ContactField $field): ?string
    {
        $fieldId = $field instanceof ContactField ? $field->id : $field;
        
        $value = $this->fieldValues->firstWhere('contact_field_id', $fieldId);
        
        return $value?->value;
    }

    /**
     * Set the value of a specific custom field.
     */
    public function setFieldValue(int|ContactField $field, ?string $value): void
    {
        $fieldId = $field instanceof ContactField ? $field->id : $field;

        $this->fieldValues()->updateOrCreate(
            ['contact_field_id' => $fieldId],
            ['value' => $value]
        );
    }

    /**
     * Format the phone number for display.
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = preg_replace('/\D/', '', $this->phone);
        
        if (strlen($phone) === 11) {
            return sprintf('(%s)%s-%s', 
                substr($phone, 0, 2),
                substr($phone, 2, 5),
                substr($phone, 7, 4)
            );
        }
        
        if (strlen($phone) === 10) {
            return sprintf('(%s)%s-%s', 
                substr($phone, 0, 2),
                substr($phone, 2, 4),
                substr($phone, 6, 4)
            );
        }
        
        return $this->phone;
    }

    /**
     * Get the phone number without formatting.
     */
    public function getRawPhoneAttribute(): string
    {
        return preg_replace('/\D/', '', $this->phone);
    }

    /**
     * Scope a query to search contacts.
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to only include contacts of a given user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
