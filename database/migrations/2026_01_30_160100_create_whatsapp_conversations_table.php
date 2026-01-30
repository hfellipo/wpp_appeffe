<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            // This is the account owner id (same semantics used elsewhere: user->accountId())
            $table->unsignedBigInteger('user_id');

            $table->string('instance_name');
            $table->string('contact_number');
            $table->string('contact_name')->nullable();

            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 500)->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
            $table->index(['user_id', 'instance_name']);
            $table->unique(['user_id', 'instance_name', 'contact_number'], 'wa_conv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};

