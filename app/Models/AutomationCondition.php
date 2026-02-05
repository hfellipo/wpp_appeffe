<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationCondition extends Model
{
    protected $fillable = [
        'automation_id',
        'position',
        'field_type',   // 'attribute' | 'custom'
        'field_key',    // 'name' | 'email' | 'phone'
        'contact_field_id',
        'operator',     // equals, not_equals, contains, is_empty, is_not_empty
        'value',
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

    public function contactField(): BelongsTo
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id');
    }

    /** Retorna o valor do campo no contato para avaliação. */
    public function getContactValue(\App\Models\Contact $contact): ?string
    {
        if ($this->field_type === 'attribute') {
            return match ($this->field_key) {
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => (string) $contact->phone,
                default => null,
            };
        }
        if ($this->field_type === 'custom' && $this->contact_field_id) {
            return $contact->getFieldValue($this->contact_field_id);
        }
        return null;
    }

    /** Verifica se a regra passa para o contato. */
    public function evaluate(\App\Models\Contact $contact): bool
    {
        $actual = $this->getContactValue($contact);
        $actual = $actual === null ? '' : trim((string) $actual);
        $expected = trim((string) ($this->value ?? ''));

        return match ($this->operator) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'contains' => $expected !== '' && str_contains($actual, $expected),
            'is_empty' => $actual === '',
            'is_not_empty' => $actual !== '',
            default => false,
        };
    }
}
