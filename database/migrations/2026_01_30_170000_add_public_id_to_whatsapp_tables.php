<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Conversations
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('whatsapp_conversations')
            ->select('id', 'public_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (!empty($row->public_id)) continue;
                    DB::table('whatsapp_conversations')
                        ->where('id', $row->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        // Avoid Blueprint::change() (requires doctrine/dbal). Enforce NOT NULL best-effort by driver.
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `whatsapp_conversations` MODIFY `public_id` CHAR(26) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "whatsapp_conversations" ALTER COLUMN "public_id" SET NOT NULL');
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->unique('public_id', 'wa_conv_public_id_unique');
            $table->index(['user_id', 'public_id'], 'wa_conv_user_public_idx');
        });

        // Messages
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('whatsapp_messages')
            ->select('id', 'public_id')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    if (!empty($row->public_id)) continue;
                    DB::table('whatsapp_messages')
                        ->where('id', $row->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `whatsapp_messages` MODIFY `public_id` CHAR(26) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "whatsapp_messages" ALTER COLUMN "public_id" SET NOT NULL');
        }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unique('public_id', 'wa_msg_public_id_unique');
            $table->index(['conversation_id', 'public_id'], 'wa_msg_conv_public_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_msg_conv_public_idx');
            $table->dropUnique('wa_msg_public_id_unique');
            $table->dropColumn('public_id');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('wa_conv_user_public_idx');
            $table->dropUnique('wa_conv_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};

