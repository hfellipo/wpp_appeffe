<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('bot_disabled')->default(false)->after('notes');
            $table->timestamp('bot_paused_until')->nullable()->after('bot_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['bot_disabled', 'bot_paused_until']);
        });
    }
};
