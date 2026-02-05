<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->onDelete('cascade');
            $table->string('type', 50); // list_added, tag_added, manual, schedule_daily, schedule_weekly, schedule_monthly, schedule_yearly, schedule_cron
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_triggers');
    }
};
