<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_attachments', function (Blueprint $table) {
            $table->id();

            $table->char('public_id', 26)->unique('wa_attach_public_id_unique');

            // This migration runs before whatsapp_messages in fresh installs/tests.
            // We create the column without FK here to avoid migration-order failures.
            $table->unsignedBigInteger('message_id')->index('wa_attach_message_id_idx');

            $table->string('type')->nullable(); // image|video|document|audio|sticker|unknown
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            // storage
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();

            // Evolution-provided URL (if any)
            $table->text('remote_url')->nullable();

            // small plaintext preview for list rendering
            $table->string('caption_preview', 500)->nullable();

            // encrypt large/sensitive payloads
            $table->longText('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['message_id', 'created_at'], 'wa_attach_msg_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_attachments');
    }
};
