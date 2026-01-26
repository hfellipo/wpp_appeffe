<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContactField extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'options',
        'required',
        'show_in_list',
        'order',
        'active',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'show_in_list' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Available field types.
     */
    public const TYPES = [
        'text' => 'Texto',
        'number' => 'Número',
        'date' => 'Data',
        'email' => 'E-mail',
        'url' => 'URL',
        'textarea' => 'Texto Longo',
        'select' => 'Lista de Opções',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($field) {
            if (empty($field->slug)) {
                $field->slug = Str::slug($field->name);
            }
        });

        static::updating(function ($field) {
            if ($field->isDirty('name') && empty($field->slug)) {
                $field->slug = Str::slug($field->name);
            }
        });
    }

    /**
     * Get the user that owns the field.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the values for this field.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Scope a query to only include active fields.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include fields shown in list.
     */
    public function scopeShowInList($query)
    {
        return $query->where('show_in_list', true);
    }

    /**
     * Scope a query to only include fields of a given user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to order by position.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }
}
