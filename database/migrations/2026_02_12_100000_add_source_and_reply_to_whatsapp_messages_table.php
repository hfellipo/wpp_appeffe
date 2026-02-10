<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('source_type', 32)->nullable()->after('automation_run_id')->comment('automation_run|funnel_stage');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('in_reply_to_message_id')->nullable()->after('remote_id');
            $table->index(['source_type', 'source_id']);
            $table->foreign('in_reply_to_message_id')->references('id')->on('whatsapp_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['in_reply_to_message_id']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id', 'in_reply_to_message_id']);
        });
    }
};
