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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();

            $table->char('public_id', 26)->unique('wa_contact_public_id_unique');

            // account owner id (same semantics: user->accountId())
            $table->unsignedBigInteger('user_id')->index();

            $table->string('instance_name')->index();

            // WhatsApp identifiers
            $table->string('contact_jid')->nullable()->index(); // e.g. 5511...@s.whatsapp.net
            $table->string('contact_number')->nullable()->index(); // digits only for convenience

            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'instance_name', 'contact_number'], 'wa_contact_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
