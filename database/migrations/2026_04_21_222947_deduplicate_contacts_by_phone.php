<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Merge contacts that share the same (user_id, phone).
     *
     * For each group of duplicates:
     *  - Keep the oldest record (lowest id) as the "winner".
     *  - Re-assign lista_contact, contact_tag, whatsapp_conversations,
     *    and automation_runs to the winner.
     *  - Hard-delete the loser rows so the unique index stays clean.
     */
    public function up(): void
    {
        $duplicates = DB::table('contacts')
            ->select('user_id', 'phone', DB::raw('MIN(id) as winner_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id', 'phone')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $row) {
            $winnerId = $row->winner_id;
            $loserIds = DB::table('contacts')
                ->where('user_id', $row->user_id)
                ->where('phone', $row->phone)
                ->where('id', '!=', $winnerId)
                ->pluck('id')
                ->all();

            if (empty($loserIds)) {
                continue;
            }

            // Move lista_contact pivot rows to the winner (skip if already linked).
            foreach ($loserIds as $loserId) {
                $listaIds = DB::table('lista_contact')
                    ->where('contact_id', $loserId)
                    ->pluck('lista_id')
                    ->all();

                foreach ($listaIds as $listaId) {
                    $exists = DB::table('lista_contact')
                        ->where('contact_id', $winnerId)
                        ->where('lista_id', $listaId)
                        ->exists();

                    if (! $exists) {
                        DB::table('lista_contact')->insert([
                            'contact_id' => $winnerId,
                            'lista_id'   => $listaId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('lista_contact')->where('contact_id', $loserId)->delete();
            }

            // Move contact_tag pivot rows to the winner.
            foreach ($loserIds as $loserId) {
                $tagIds = DB::table('contact_tag')
                    ->where('contact_id', $loserId)
                    ->pluck('tag_id')
                    ->all();

                foreach ($tagIds as $tagId) {
                    $exists = DB::table('contact_tag')
                        ->where('contact_id', $winnerId)
                        ->where('tag_id', $tagId)
                        ->exists();

                    if (! $exists) {
                        DB::table('contact_tag')->insert([
                            'contact_id' => $winnerId,
                            'tag_id'     => $tagId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('contact_tag')->where('contact_id', $loserId)->delete();
            }

            // Re-point whatsapp_conversations and automation_runs to the winner.
            DB::table('whatsapp_conversations')
                ->whereIn('contact_id', $loserIds)
                ->update(['contact_id' => $winnerId]);

            if (DB::getSchemaBuilder()->hasTable('automation_runs')) {
                DB::table('automation_runs')
                    ->whereIn('contact_id', $loserIds)
                    ->update(['contact_id' => $winnerId]);
            }

            // Hard-delete the loser rows (bypasses soft-delete so unique index is freed).
            DB::table('contacts')->whereIn('id', $loserIds)->delete();
        }
    }

    public function down(): void
    {
        // Deduplication is irreversible by nature.
    }
};
