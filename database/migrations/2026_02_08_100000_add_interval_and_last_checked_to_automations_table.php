<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->unsignedSmallInteger('interval_minutes')->default(15)->after('condition_logic');
            $table->timestamp('last_checked_at')->nullable()->after('interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['interval_minutes', 'last_checked_at']);
        });
    }
};
