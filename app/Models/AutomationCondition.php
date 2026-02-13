<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationCondition extends Model
{
    protected $fillable = [
        'automation_id',
        'position',
        'field_type',   // 'attribute' | 'custom' | 'message_status'
        'field_key',    // 'name' | 'email' | 'phone' (when attribute)
        'contact_field_id',
        'operator',     // equals, not_equals, ... ou is_sent, is_delivered, is_read, etc. (when message_status)
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

    /** Retorna o valor do campo no contato para avaliação (atributo ou custom). */
    public function getContactValue(Contact $contact): ?string
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

    /**
     * Última mensagem enviada (pelo app) ao contato.
     * Usada nas condições "Se" por status da mensagem.
     */
    public static function getLastOutboundMessageForContact(Contact $contact): ?WhatsAppMessage
    {
        return WhatsAppMessage::query()
            ->where('direction', 'out')
            ->whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->orderByDesc('sent_at')
            ->first();
    }

    /**
     * Verifica se a regra passa para o contato.
     * Suporta: atributo do contato, campo personalizado e condição "Se" por status da mensagem.
     */
    public function evaluate(Contact $contact): bool
    {
        if ($this->field_type === 'message_status') {
            return $this->evaluateMessageStatus($contact);
        }

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

    /** Avalia a condição "Se" baseada no status da última mensagem enviada ao contato. */
    private function evaluateMessageStatus(Contact $contact): bool
    {
        $lastMessage = self::getLastOutboundMessageForContact($contact);

        return match ($this->operator) {
            'is_sent' => $lastMessage !== null && $lastMessage->sent_at !== null,
            'is_delivered' => $lastMessage !== null && $lastMessage->delivered_at !== null,
            'is_read' => $lastMessage !== null && $lastMessage->read_at !== null,
            'is_not_delivered' => $lastMessage === null || $lastMessage->delivered_at === null,
            'is_not_read' => $lastMessage === null || $lastMessage->read_at === null,
            default => false,
        };
    }
}
