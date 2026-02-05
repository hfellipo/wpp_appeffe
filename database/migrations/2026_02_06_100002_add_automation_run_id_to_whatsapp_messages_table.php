<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('automation_run_id')->nullable()->after('conversation_id');
            $table->foreign('automation_run_id')->references('id')->on('automation_runs')->nullOnDelete();
            $table->index('automation_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['automation_run_id']);
            $table->dropIndex(['automation_run_id']);
        });
    }
};
