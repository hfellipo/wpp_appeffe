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
     * Normaliza e formata o telefone ao salvar: (XX)XXXXX-XXXX ou (XX)XXXX-XXXX.
     * Remove zero à esquerda (ex: 031994234090 -> (31)99423-4090) para evitar JID inválido no WhatsApp.
     */
    protected function phone(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }
                $digits = preg_replace('/\D/', '', $value) ?: '';
                if ($digits === '') {
                    return $value;
                }
                if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                    $digits = substr($digits, 1);
                }
                if (strlen($digits) === 11) {
                    return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
                }
                if (strlen($digits) === 10) {
                    return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
                }
                return $value;
            },
        );
    }

    /**
     * Prefixos de 2 dígitos que são código de país (não Brasil). Não adicionamos 55 nesses casos.
     */
    private static function nonBrazilTwoDigitCountryCodes(): array
    {
        return [
            '1', '7', '20', '27', '30', '32', '33', '34', '36', '39', '40', '41', '43', '44', '45',
            '46', '47', '48', '49', '51', '52', '53', '54', '56', '57', '58', '60', '61', '62', '63', '64',
            '65', '66', '84', '86', '90', '92', '93', '94', '95', '98',
        ];
    }

    /**
     * Número no formato E.164 para envio no WhatsApp. Universal: qualquer país.
     * Só adiciona +55 quando for claramente número local BR (DDD 11-99), não outro país (ex: 61 Austrália).
     */
    public function getPhoneForWhatsappAttribute(): string
    {
        $digits = preg_replace('/\D/', '', (string) ($this->attributes['phone'] ?? '')) ?: '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (str_starts_with($digits, '550') && strlen($digits) >= 13) {
            $digits = '55' . substr($digits, 3);
        }
        if (strlen($digits) >= 12 && strlen($digits) <= 15) {
            return $digits;
        }
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            return $digits;
        }
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $prefix2 = substr($digits, 0, 2);
            if (! in_array($prefix2, self::nonBrazilTwoDigitCountryCodes(), true)) {
                $ddd = (int) $prefix2;
                if ($ddd >= 11 && $ddd <= 99) {
                    return '55' . $digits;
                }
            }
            return $digits;
        }
        if (strlen($digits) >= 9 && strlen($digits) <= 15) {
            return $digits;
        }
        return $digits;
    }

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
