<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppGroup extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'public_id',
        'user_id',
        'instance_name',
        'group_jid',
        'subject',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }
}
