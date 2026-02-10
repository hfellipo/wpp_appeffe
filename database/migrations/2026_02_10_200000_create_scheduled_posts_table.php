<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('target_type', 20); // 'group' | 'list' | 'tag'
            $table->unsignedBigInteger('target_id'); // whatsapp_conversations.id, listas.id, or tags.id
            $table->text('message');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
