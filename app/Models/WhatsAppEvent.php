<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppEvent extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_events';

    protected $fillable = [
        'user_id',
        'type',
        'payload',
    ];

    protected $casts = [
        // Stored as encrypted JSON string in longText
        'payload' => 'encrypted:array',
    ];
}

