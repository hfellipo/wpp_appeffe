<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Conta" dona dos dados. Para contas "raiz", account_id = id.
            $table->unsignedBigInteger('account_id')->nullable()->after('status');
            $table->index('account_id');
        });

        // Preenche para usuários existentes (conta raiz)
        DB::table('users')
            ->whereNull('account_id')
            ->update(['account_id' => DB::raw('id')]);

        Schema::table('users', function (Blueprint $table) {
            $table
                ->foreign('account_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};

