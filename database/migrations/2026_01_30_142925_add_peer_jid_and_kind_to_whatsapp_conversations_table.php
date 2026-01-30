<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration file has an earlier timestamp than the base table creation.
        // In fresh installs / sqlite tests, the table doesn't exist yet. Don't fail.
        if (!Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'kind')) {
                $table->string('kind')->default('direct');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'peer_jid')) {
                $table->string('peer_jid')->nullable();
            }
        });

        // Backfill for existing rows (direct)
        try {
            DB::table('whatsapp_conversations')
                ->whereNull('peer_jid')
                ->orWhere('peer_jid', '=', '')
                ->update([
                    'peer_jid' => DB::raw("CONCAT(contact_number, '@s.whatsapp.net')"),
                    'kind' => DB::raw("COALESCE(NULLIF(kind,''), 'direct')"),
                ]);
        } catch (\Throwable $e) {
            // ignore
        }

        // Index changes (best-effort, ignore if already applied / driver differs)
        try {
            DB::statement('ALTER TABLE `whatsapp_conversations` DROP INDEX `wa_conv_unique`');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::statement('CREATE INDEX `wa_conv_peer_jid_idx` ON `whatsapp_conversations` (`peer_jid`)');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::statement('CREATE UNIQUE INDEX `wa_conv_peer_unique` ON `whatsapp_conversations` (`user_id`, `instance_name`, `peer_jid`)');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        // best-effort rollback; avoid failing in sqlite tests
        try { DB::statement('DROP INDEX `wa_conv_peer_unique` ON `whatsapp_conversations`'); } catch (\Throwable $e) {}
        try { DB::statement('DROP INDEX `wa_conv_peer_jid_idx` ON `whatsapp_conversations`'); } catch (\Throwable $e) {}

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'peer_jid')) {
                $table->dropColumn('peer_jid');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
