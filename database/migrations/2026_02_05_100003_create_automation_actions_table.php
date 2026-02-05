<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->onDelete('cascade');
            $table->string('type', 50); // send_whatsapp_message, add_to_list, add_tag, wait_delay
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_actions');
    }
};
