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
        Schema::table('funnel_stage_rules', function (Blueprint $table) {
            // specific_reply: keyword to match in incoming message
            $table->string('keyword')->nullable()->after('trigger_config');
            // action: move only, send only, or move + send
            $table->enum('action_type', ['move', 'send', 'move_and_send'])->default('move')->after('keyword');
            $table->text('action_message')->nullable()->after('action_type');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_stage_rules', function (Blueprint $table) {
            $table->dropColumn(['keyword', 'action_type', 'action_message']);
        });
    }
};
