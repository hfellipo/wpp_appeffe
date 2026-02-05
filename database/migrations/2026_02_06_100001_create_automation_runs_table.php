<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->timestamp('ran_at');
            $table->string('status', 20)->default('success'); // success, failed, partial
            $table->json('metadata')->nullable(); // e.g. actions_executed, error_message
            $table->timestamps();

            $table->index(['contact_id', 'ran_at']);
            $table->index(['automation_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
