<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite (used in tests) doesn't support "ALTER TABLE .. MODIFY".
        // Also, our create-table migrations already use LONGTEXT for these columns.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // We store encrypted payloads, so JSON column type cannot be used.
        if (Schema::hasTable('whatsapp_messages')) {
            DB::statement('ALTER TABLE `whatsapp_messages` MODIFY `raw_payload` LONGTEXT NULL');
        }
        if (Schema::hasTable('whatsapp_attachments')) {
            DB::statement('ALTER TABLE `whatsapp_attachments` MODIFY `raw_payload` LONGTEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Best-effort rollback to JSON (may fail if data is not valid JSON).
        if (Schema::hasTable('whatsapp_messages')) {
            DB::statement('ALTER TABLE `whatsapp_messages` MODIFY `raw_payload` JSON NULL');
        }
        if (Schema::hasTable('whatsapp_attachments')) {
            DB::statement('ALTER TABLE `whatsapp_attachments` MODIFY `raw_payload` JSON NULL');
        }
    }
};
