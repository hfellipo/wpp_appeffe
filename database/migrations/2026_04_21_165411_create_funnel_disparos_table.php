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
        Schema::create('funnel_disparos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('funnel_stage_id');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime')->nullable();
            $table->enum('mode', ['sequential', 'round_robin', 'random'])->default('sequential');
            $table->unsignedSmallInteger('delay_seconds')->default(0);
            $table->json('contact_ids');
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('funnel_stage_id');
            $table->foreign('funnel_stage_id')->references('id')->on('funnel_stages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_disparos');
    }
};
