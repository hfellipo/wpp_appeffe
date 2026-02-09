<?php

use App\Http\Controllers\AutomationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactFieldController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\ListaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WhatsAppEvolutionController;
use App\Http\Controllers\WhatsAppInboxController;
use App\Http\Controllers\WhatsAppStreamController;
use App\Http\Controllers\EvolutionWebhookController;
use App\Http\Controllers\Admin\AccountUsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// Rota de debug para testar se o Laravel está recebendo requisições
Route::match(['get', 'post'], '/debug/test', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'message' => 'Laravel está funcionando!',
        'path_info' => $request->getPathInfo(),
        'request_uri' => $request->server->get('REQUEST_URI'),
        'script_name' => $request->server->get('SCRIPT_NAME'),
        'full_url' => $request->fullUrl(),
        'method' => $request->method(),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('debug.test');

Route::match(['get', 'post'], '/public/debug/test', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'message' => 'Laravel está funcionando (via /public)!',
        'path_info' => $request->getPathInfo(),
        'request_uri' => $request->server->get('REQUEST_URI'),
        'script_name' => $request->server->get('SCRIPT_NAME'),
        'full_url' => $request->fullUrl(),
        'method' => $request->method(),
        'timestamp' => now()->toIso8601String(),
        'note' => 'Se você vê esta mensagem, o Laravel está recebendo requisições com /public',
    ]);
})->name('debug.test.public');

// Evolution API Webhook (sem auth; CSRF excluído em VerifyCsrfToken)
Route::post('/webhook/evolution', [EvolutionWebhookController::class, 'handle'])->name('webhook.evolution');
Route::match(['get', 'post'], '/webhook/evolution/test', function (Request $request) {
    return response()->json([
        'ok' => true,
        'method' => $request->method(),
        'path' => $request->getPathInfo(),
        'received' => $request->all(),
        'headers' => $request->headers->all(),
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('webhook.evolution.test');
// Quando Evolution usa "webhook by events", chama ex: /webhook/evolution/messages-upsert
Route::post('/webhook/evolution/{path}', [EvolutionWebhookController::class, 'handle'])->name('webhook.evolution.path');

// API de teste simples - retorna apenas status básico
Route::get('/api/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API está funcionando!',
        'timestamp' => now()->toIso8601String(),
        'server_time' => now()->format('Y-m-d H:i:s'),
    ]);
})->name('api.ping');

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
    // WhatsApp Inbox (separado do Chatify)
    Route::prefix('whatsapp')->name('whatsapp.inbox.')->group(function () {
        Route::get('/', [WhatsAppInboxController::class, 'index'])->name('index');
        Route::get('/stream', [WhatsAppStreamController::class, 'stream'])->name('stream');
        Route::get('/api/contacts', [WhatsAppInboxController::class, 'contacts'])->name('api.contacts');
        Route::post('/api/conversations/start', [WhatsAppInboxController::class, 'startConversation'])->name('api.conversations.start');
        Route::get('/api/conversations', [WhatsAppInboxController::class, 'conversations'])->name('api.conversations');
        Route::patch('/api/conversations/{conversation}', [WhatsAppInboxController::class, 'updateConversation'])->name('api.conversations.update');
        Route::get('/api/conversations/{conversation}/avatar', [WhatsAppInboxController::class, 'avatar'])->name('api.conversations.avatar');
        Route::get('/api/conversations/{conversation}/app-contact', [WhatsAppInboxController::class, 'appContact'])->name('api.conversations.app-contact');
        Route::get('/api/conversations/{conversation}/messages', [WhatsAppInboxController::class, 'messages'])->name('api.messages');
        Route::post('/api/conversations/{conversation}/send', [WhatsAppInboxController::class, 'send'])->name('api.send');
        Route::post('/api/conversations/{conversation}/send-media', [WhatsAppInboxController::class, 'sendMedia'])->name('api.send-media');
        Route::get('/api/attachments/{attachment}', [WhatsAppInboxController::class, 'showAttachment'])->name('attachments.show');
    });

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

    // Gestão de usuários da conta (apenas admin)
    Route::middleware('admin')->prefix('settings/users')->name('settings.users.')->group(function () {
        Route::get('/', [AccountUsersController::class, 'index'])->name('index');
        Route::post('/', [AccountUsersController::class, 'store'])->name('store');
        Route::put('/{user}', [AccountUsersController::class, 'update'])->name('update');
        Route::delete('/{user}', [AccountUsersController::class, 'destroy'])->name('destroy');
    });

    // Rota temporária para limpar cache (apenas admin) - REMOVER após resolver o problema
    Route::middleware('admin')->post('/admin/clear-cache', function () {
        try {
            \Artisan::call('optimize:clear');
            \Artisan::call('route:clear');
            \Artisan::call('config:clear');
            \Artisan::call('cache:clear');
            \Artisan::call('view:clear');
            \Artisan::call('event:clear');
            
            // Remover arquivos de cache compilados
            $cacheFiles = [
                base_path('bootstrap/cache/config.php'),
                base_path('bootstrap/cache/routes-v7.php'),
                base_path('bootstrap/cache/services.php'),
                base_path('bootstrap/cache/packages.php'),
            ];
            
            foreach ($cacheFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Cache limpo com sucesso!',
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar cache: ' . $e->getMessage(),
            ], 500);
        }
    })->name('admin.clear-cache');

    // Evolution API / WhatsApp (novo, sem duplicidades)
    Route::prefix('settings/whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [WhatsAppEvolutionController::class, 'index'])->name('index');
        Route::get('/api', [WhatsAppEvolutionController::class, 'apiIndex'])->name('api');
        Route::get('/status', [WhatsAppEvolutionController::class, 'status'])->name('status');
        Route::post('/instance', [WhatsAppEvolutionController::class, 'createInstance'])->name('instance.create');
        Route::get('/connect/{instance}', [WhatsAppEvolutionController::class, 'connect'])->name('connect');
        Route::get('/state/{instance}', [WhatsAppEvolutionController::class, 'state'])->name('state');
        Route::post('/disconnect/{instance}', [WhatsAppEvolutionController::class, 'disconnect'])->name('disconnect');
        Route::post('/delete/{instance}', [WhatsAppEvolutionController::class, 'delete'])->name('delete');
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

    // Listas (lists) - create/list/edit/delete listas and manage contacts
    Route::resource('listas', ListaController::class);
    Route::get('listas/{lista}/contacts', [ListaController::class, 'editContacts'])->name('listas.contacts.edit');
    Route::post('listas/{lista}/contacts', [ListaController::class, 'updateContacts'])->name('listas.contacts.update');
    Route::post('listas/{lista}/contacts/detach-contact', [ListaController::class, 'detachContact'])->name('listas.contacts.detach-contact');
    Route::post('listas/{lista}/contacts/detach-whatsapp', [ListaController::class, 'detachWhatsAppContact'])->name('listas.contacts.detach-whatsapp');

    // Tags - create/list/edit/delete tags and filter contacts by tag
    Route::resource('tags', TagController::class)->except(['show']);
    Route::get('tags/{tag}/contacts', [TagController::class, 'editContacts'])->name('tags.contacts.edit');
    Route::post('tags/{tag}/contacts', [TagController::class, 'updateContacts'])->name('tags.contacts.update');

    // Automação (trigger → condition → action)
    Route::get('/automacao', [AutomationController::class, 'index'])->name('automacao.index');
    Route::get('/automacao/create', [AutomationController::class, 'create'])->name('automacao.create');
    Route::post('/automacao', [AutomationController::class, 'store'])->name('automacao.store');
    Route::get('/automacao/{automacao}/edit', [AutomationController::class, 'edit'])->name('automacao.edit');
    Route::put('/automacao/{automacao}', [AutomationController::class, 'update'])->name('automacao.update');
    Route::delete('/automacao/{automacao}', [AutomationController::class, 'destroy'])->name('automacao.destroy');
    Route::post('/automacao/{automacao}/toggle', [AutomationController::class, 'toggle'])->name('automacao.toggle');
    Route::delete('/automacao/{automacao}/actions/{action}', [AutomationController::class, 'destroyAction'])->name('automacao.actions.destroy');
    Route::get('/automacao/{automacao}/test', [AutomationController::class, 'test'])->name('automacao.test');
    Route::post('/automacao/{automacao}/test', [AutomationController::class, 'runTest'])->name('automacao.runTest');
});

require __DIR__.'/auth.php';
