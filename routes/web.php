<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactFieldController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\EvolutionApiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Evolution API Webhook (must be public, but we'll validate via API key)
// Rotas normais (sem /public)
Route::post('/webhook/evolution', [EvolutionApiController::class, 'webhook'])->name('evolution.webhook');
Route::get('/webhook/evolution/test', [EvolutionApiController::class, 'testWebhook'])->name('evolution.webhook.test');
Route::post('/webhook/evolution/test', [EvolutionApiController::class, 'testWebhookPost'])->name('evolution.webhook.test.post');

// Rotas com /public (para servidores que redirecionam automaticamente)
// IMPORTANTE: Se o servidor adiciona /public automaticamente, estas rotas são necessárias
Route::post('/public/webhook/evolution', [EvolutionApiController::class, 'webhook'])->name('evolution.webhook.public');
Route::get('/public/webhook/evolution/test', [EvolutionApiController::class, 'testWebhook'])->name('evolution.webhook.test.public');
Route::post('/public/webhook/evolution/test', [EvolutionApiController::class, 'testWebhookPost'])->name('evolution.webhook.test.post.public');

// API de teste simples - retorna apenas status básico
Route::get('/api/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API está funcionando!',
        'timestamp' => now()->toIso8601String(),
        'server_time' => now()->format('Y-m-d H:i:s'),
    ]);
})->name('api.ping');

// API de teste HTTP - para verificar acesso e debug
Route::match(['get', 'post', 'put', 'patch', 'delete'], '/api/test', function (Request $request) {
    $controller = app(EvolutionApiController::class);
    $webhookUrl = $controller->getWebhookUrl();
    
    return response()->json([
        'status' => 'success',
        'message' => 'API de teste funcionando corretamente!',
        'timestamp' => now()->toIso8601String(),
        'server_time' => now()->format('Y-m-d H:i:s'),
        'timezone' => config('app.timezone'),
        
        // Informações da requisição
        'request' => [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'ip' => $request->ip(),
            'ips' => $request->ips(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
        ],
        
        // Headers recebidos
        'headers' => $request->headers->all(),
        
        // Dados recebidos
        'data' => [
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'json_data' => $request->json() ? $request->json()->all() : null,
            'raw_body' => $request->getContent(),
            'all_data' => $request->all(),
        ],
        
        // Informações do servidor
        'server' => [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
        ],
        
        // Informações do webhook
        'webhook' => [
            'webhook_url' => $webhookUrl,
            'webhook_requires_public' => env('WEBHOOK_REQUIRES_PUBLIC', false),
            'note' => 'Use esta URL para configurar o webhook na Evolution API',
        ],
        
        // Comandos úteis para teste
        'test_commands' => [
            'curl_get' => "curl -X GET '{$request->fullUrl()}'",
            'curl_post' => "curl -X POST '{$request->fullUrl()}' -H 'Content-Type: application/json' -d '{\"test\":\"data\"}'",
            'curl_webhook' => "curl -X POST '{$webhookUrl}' -H 'Content-Type: application/json' -d '{\"event\":\"test\",\"data\":{}}'",
        ],
    ], 200, [
        'Content-Type' => 'application/json',
        'X-Test-API' => 'true',
    ]);
})->name('api.test');

// Rotas com /public para API de teste (para servidores que redirecionam automaticamente)
Route::get('/public/api/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API está funcionando!',
        'timestamp' => now()->toIso8601String(),
        'server_time' => now()->format('Y-m-d H:i:s'),
        'note' => 'Acessado via /public/api/ping',
    ]);
})->name('api.ping.public');

Route::match(['get', 'post', 'put', 'patch', 'delete'], '/public/api/test', function (Request $request) {
    $controller = app(EvolutionApiController::class);
    $webhookUrl = $controller->getWebhookUrl();
    
    return response()->json([
        'status' => 'success',
        'message' => 'API de teste funcionando corretamente!',
        'timestamp' => now()->toIso8601String(),
        'server_time' => now()->format('Y-m-d H:i:s'),
        'timezone' => config('app.timezone'),
        'note' => 'Acessado via /public/api/test',
        
        // Informações da requisição
        'request' => [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'ip' => $request->ip(),
            'ips' => $request->ips(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
        ],
        
        // Headers recebidos
        'headers' => $request->headers->all(),
        
        // Dados recebidos
        'data' => [
            'query_params' => $request->query(),
            'post_data' => $request->post(),
            'json_data' => $request->json() ? $request->json()->all() : null,
            'raw_body' => $request->getContent(),
            'all_data' => $request->all(),
        ],
        
        // Informações do servidor
        'server' => [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
        ],
        
        // Informações do webhook
        'webhook' => [
            'webhook_url' => $webhookUrl,
            'webhook_requires_public' => env('WEBHOOK_REQUIRES_PUBLIC', false),
            'note' => 'Use esta URL para configurar o webhook na Evolution API',
        ],
        
        // Comandos úteis para teste
        'test_commands' => [
            'curl_get' => "curl -X GET '{$request->fullUrl()}'",
            'curl_post' => "curl -X POST '{$request->fullUrl()}' -H 'Content-Type: application/json' -d '{\"test\":\"data\"}'",
            'curl_webhook' => "curl -X POST '{$webhookUrl}' -H 'Content-Type: application/json' -d '{\"event\":\"test\",\"data\":{}}'",
        ],
    ], 200, [
        'Content-Type' => 'application/json',
        'X-Test-API' => 'true',
    ]);
})->name('api.test.public');

// Debug endpoint to test database connection
Route::get('/debug/db-test', function () {
    try {
        $extensions = [
            'mbstring' => extension_loaded('mbstring'),
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
        ];
        
        $dbTest = null;
        try {
            \DB::connection()->getPdo();
            $dbTest = 'Database connection OK';
            $tables = \DB::select('SHOW TABLES');
        } catch (\Exception $e) {
            $dbTest = 'Database connection FAILED: ' . $e->getMessage();
            $tables = [];
        }
        
        return response()->json([
            'php_version' => PHP_VERSION,
            'extensions' => $extensions,
            'database' => $dbTest,
            'tables_count' => count($tables),
            'laravel_version' => app()->version(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

    // Evolution API / WhatsApp routes
    Route::prefix('settings/whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [EvolutionApiController::class, 'index'])->name('index');
        Route::post('/connect', [EvolutionApiController::class, 'connect'])->name('connect');
        Route::get('/qrcode', [EvolutionApiController::class, 'qrcode'])->name('qrcode');
        Route::get('/status', [EvolutionApiController::class, 'status'])->name('status');
        Route::post('/logout', [EvolutionApiController::class, 'logout'])->name('logout');
        Route::delete('/delete', [EvolutionApiController::class, 'delete'])->name('delete');
        Route::post('/webhook', [EvolutionApiController::class, 'configureWebhook'])->name('webhook.configure');
    });

    // Contact Fields routes (MUST be before resource to avoid conflicts)
    Route::prefix('contacts/fields')->name('contacts.fields.')->group(function () {
        Route::get('/', [ContactFieldController::class, 'index'])->name('index');
        Route::get('/create', [ContactFieldController::class, 'create'])->name('create');
        Route::post('/', [ContactFieldController::class, 'store'])->name('store');
        Route::post('/reorder', [ContactFieldController::class, 'reorder'])->name('reorder');
        Route::get('/{field}/edit', [ContactFieldController::class, 'edit'])->name('edit');
        Route::put('/{field}', [ContactFieldController::class, 'update'])->name('update');
        Route::patch('/{field}/toggle', [ContactFieldController::class, 'toggle'])->name('toggle');
        Route::delete('/{field}', [ContactFieldController::class, 'destroy'])->name('destroy');
    });

    // Contact Import routes (MUST be before resource to avoid conflicts)
    Route::prefix('contacts/import')->name('contacts.import.')->group(function () {
        Route::get('/', [ContactImportController::class, 'index'])->name('index');
        Route::post('/upload', [ContactImportController::class, 'upload'])->name('upload');
        Route::post('/process', [ContactImportController::class, 'process'])->name('process');
        Route::post('/process-chunk', [ContactImportController::class, 'processChunk'])->name('process-chunk');
        Route::post('/cancel', [ContactImportController::class, 'cancel'])->name('cancel');
        Route::get('/status', [ContactImportController::class, 'status'])->name('status');
        Route::get('/template', [ContactImportController::class, 'template'])->name('template');
    });

    // Contacts resource routes
    Route::resource('contacts', ContactController::class);
});

require __DIR__.'/auth.php';
