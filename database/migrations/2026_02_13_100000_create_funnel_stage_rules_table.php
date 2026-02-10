<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_stage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_stage_id')->constrained('funnel_stages')->cascadeOnDelete();
            $table->string('trigger_type', 32); // whatsapp_replied, tag_added, list_added
            $table->json('trigger_config')->nullable(); // e.g. {"tag_id":1} or {"lista_id":1}
            $table->foreignId('target_stage_id')->constrained('funnel_stages')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['funnel_stage_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_stage_rules');
    }
};
