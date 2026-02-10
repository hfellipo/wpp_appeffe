<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'evolution_api' => [
        'url' => env('EVOLUTION_API_URL'),
        'key' => env('EVOLUTION_API_KEY'),
        // true = processa webhook na hora (mensagens chegam sem rodar queue:work)
        'webhook_sync' => env('EVOLUTION_WEBHOOK_SYNC', false),
        // true = loga no storage/logs/laravel.log a resposta de participantes (telefone/nome) para debug
        'debug_participants' => env('EVOLUTION_DEBUG_PARTICIPANTS', false),
    ],

    // Token para a URL de cron dos posts agendados (processar sem abrir a página)
    'scheduled_posts_cron_token' => env('SCHEDULED_POSTS_CRON_TOKEN'),

];
