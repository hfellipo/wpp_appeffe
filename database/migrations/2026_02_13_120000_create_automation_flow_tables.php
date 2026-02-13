<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->onDelete('cascade');
            $table->string('type', 50); // start, send_message, delay, add_tag, add_list
            $table->decimal('position_x', 12, 2)->default(0);
            $table->decimal('position_y', 12, 2)->default(0);
            $table->json('config')->nullable();
            $table->string('label', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('automation_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->onDelete('cascade');
            $table->foreignId('source_node_id')->constrained('automation_nodes')->onDelete('cascade');
            $table->foreignId('target_node_id')->constrained('automation_nodes')->onDelete('cascade');
            $table->string('source_handle', 50)->default('default');
            $table->string('target_handle', 50)->default('input');
            $table->timestamps();

            $table->unique(['automation_id', 'source_node_id', 'target_node_id', 'source_handle', 'target_handle'], 'flow_edges_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_edges');
        Schema::dropIfExists('automation_nodes');
    }
};
