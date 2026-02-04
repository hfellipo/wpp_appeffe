<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('participant_jid', 64)->nullable()->after('direction');
            $table->string('sender_name', 255)->nullable()->after('participant_jid');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('last_message_sender', 255)->nullable()->after('last_message_preview');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['participant_jid', 'sender_name']);
        });
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn('last_message_sender');
        });
    }
};
