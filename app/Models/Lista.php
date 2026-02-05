<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lista extends Model
{
    use HasFactory;

    protected $table = 'listas';

    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * Get the user (account) that owns the list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contacts from the contacts table attached to this list.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'lista_contact')
            ->withTimestamps();
    }

    /**
     * WhatsApp contacts attached to this list.
     */
    public function whatsappContacts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppContact::class, 'lista_whatsapp_contact', 'lista_id', 'whatsapp_contact_id')
            ->withTimestamps();
    }

    /**
     * Scope to only include listas for the given user (account).
     */
    public function scopeForUser($query, int $userId): mixed
    {
        return $query->where('user_id', $userId);
    }
}
