<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppAttachment extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_attachments';

    protected $fillable = [
        'public_id',
        'message_id',
        'type',
        'mime',
        'size',
        'storage_disk',
        'storage_path',
        'remote_url',
        'caption_preview',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'encrypted:array',
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
