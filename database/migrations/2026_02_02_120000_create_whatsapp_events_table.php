<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_events', function (Blueprint $table) {
            $table->id();

            // account owner id (same semantics: user->accountId())
            $table->unsignedBigInteger('user_id')->index();

            // SSE event name (e.g. wa.message.created)
            $table->string('type', 80)->index();

            // Encrypted payload (array) => stored as text
            $table->longText('payload')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'id'], 'wa_events_user_id_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_events');
    }
};

