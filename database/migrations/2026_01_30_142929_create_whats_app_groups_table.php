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
        Schema::create('whatsapp_groups', function (Blueprint $table) {
            $table->id();

            $table->char('public_id', 26)->unique('wa_group_public_id_unique');

            // account owner id (same semantics: user->accountId())
            $table->unsignedBigInteger('user_id')->index();

            $table->string('instance_name')->index();

            // Group identifiers
            $table->string('group_jid')->index(); // e.g. 12345-678@g.us

            $table->string('subject')->nullable();
            $table->text('description')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'instance_name', 'group_jid'], 'wa_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_groups');
    }
};
