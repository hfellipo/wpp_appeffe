<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('user_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['contact_id']);
        });
    }
};
