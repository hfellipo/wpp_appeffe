<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->timestamp('resume_at')->nullable()->after('metadata');
            $table->unsignedSmallInteger('resume_from_position')->nullable()->after('resume_at');
            $table->index('resume_at');
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropIndex(['resume_at']);
            $table->dropColumn(['resume_at', 'resume_from_position']);
        });
    }
};
