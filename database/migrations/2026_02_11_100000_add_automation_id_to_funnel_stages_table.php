<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->foreignId('automation_id')->nullable()->after('color')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropForeign(['automation_id']);
        });
    }
};
