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
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('kind')->default('direct')->after('public_id');
            $table->string('peer_jid')->nullable()->after('kind'); // e.g. 5511...@s.whatsapp.net OR 123-456@g.us
        });

        // Backfill for existing rows (direct)
        DB::table('whatsapp_conversations')
            ->whereNull('peer_jid')
            ->orWhere('peer_jid', '=', '')
            ->update([
                'peer_jid' => DB::raw("CONCAT(contact_number, '@s.whatsapp.net')"),
                'kind' => DB::raw("COALESCE(NULLIF(kind,''), 'direct')"),
            ]);

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            // Replace uniqueness constraint to support groups safely
            $table->dropUnique('wa_conv_unique');
            $table->index(['user_id', 'instance_name', 'peer_jid'], 'wa_conv_peer_idx');
            $table->unique(['user_id', 'instance_name', 'peer_jid'], 'wa_conv_peer_unique');
            $table->index('peer_jid', 'wa_conv_peer_jid_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('wa_conv_peer_idx');
            $table->dropUnique('wa_conv_peer_unique');
            $table->dropIndex('wa_conv_peer_jid_idx');

            // restore old unique (best-effort)
            $table->unique(['user_id', 'instance_name', 'contact_number'], 'wa_conv_unique');

            $table->dropColumn('peer_jid');
            $table->dropColumn('kind');
        });
    }
};
