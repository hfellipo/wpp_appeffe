<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Para grupos: nome customizado pelo usuário e marcar "criado por mim".
     */
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('custom_contact_name', 255)->nullable()->after('contact_name');
            $table->boolean('user_marked_owner')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn(['custom_contact_name', 'user_marked_owner']);
        });
    }
};
