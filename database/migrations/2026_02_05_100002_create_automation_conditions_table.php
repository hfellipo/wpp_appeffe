<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->onDelete('cascade');
            $table->string('type', 50); // always_yes, always_no, contact_in_list, contact_has_tag
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_conditions');
    }
};
