<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('message');
            $table->string('image_mime', 100)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_mime']);
        });
    }
};
