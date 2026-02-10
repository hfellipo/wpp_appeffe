<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('id');
        });

        foreach (\App\Models\Funnel::all() as $funnel) {
            $funnel->update(['token' => Str::random(32)]);
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->string('token', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
