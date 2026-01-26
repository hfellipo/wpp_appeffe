<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactFieldController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\EvolutionApiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Evolution API Webhook (must be public, but we'll validate via API key)
Route::post('/webhook/evolution', [EvolutionApiController::class, 'webhook'])->name('evolution.webhook');

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
