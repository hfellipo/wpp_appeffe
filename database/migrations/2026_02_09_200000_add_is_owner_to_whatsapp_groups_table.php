<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Indica se o dono da conta (instância) é o criador do grupo (true) ou apenas participante (false).
     */
    public function up(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->boolean('is_owner')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
