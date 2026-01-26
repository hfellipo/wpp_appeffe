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
        Schema::create('contact_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Nome do campo (ex: "Empresa", "Cargo", "Aniversário")
            $table->string('slug')->nullable(); // Slug para uso interno
            $table->string('type')->default('text'); // text, number, date, email, url, textarea, select
            $table->json('options')->nullable(); // Para campos tipo select
            $table->boolean('required')->default(false);
            $table->boolean('show_in_list')->default(true); // Mostrar na listagem
            $table->integer('order')->default(0); // Ordem de exibição
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_fields');
    }
};
