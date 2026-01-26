<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactFieldController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
