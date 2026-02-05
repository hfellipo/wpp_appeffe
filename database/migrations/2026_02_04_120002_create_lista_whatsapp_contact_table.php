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
        Schema::create('lista_whatsapp_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_id')->constrained('listas')->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['lista_id', 'whatsapp_contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lista_whatsapp_contact');
    }
};
