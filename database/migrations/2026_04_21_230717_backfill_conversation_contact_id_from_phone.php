<?php

use App\Models\Contact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * For every whatsapp_conversation that has contact_number but no contact_id:
     *  1. Find existing contact by normalized phone (same user_id).
     *  2. If found, link it.
     *  3. If not found, create a new Contact so the number is always registered.
     */
    public function up(): void
    {
        $conversations = DB::table('whatsapp_conversations')
            ->whereNull('contact_id')
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->whereNull('kind')  // direct conversations only (null = direct)
            ->orWhere(function ($q) {
                $q->whereNull('contact_id')
                  ->whereNotNull('contact_number')
                  ->where('contact_number', '!=', '')
                  ->where('kind', 'direct');
            })
            ->get(['id', 'user_id', 'contact_number', 'contact_name']);

        foreach ($conversations as $conv) {
            $number   = (string) $conv->contact_number;
            $userId   = (int) $conv->user_id;
            $dispName = (string) ($conv->contact_name ?? '');

            // Strip Brazil country code for storage.
            $local = $number;
            if (strlen($local) >= 12 && str_starts_with($local, '55')) {
                $candidate = substr($local, 2);
                if (strlen($candidate) === 10 || strlen($candidate) === 11) {
                    $local = $candidate;
                }
            }
            $normalized = Contact::normalizePhoneForStorage($local);
            if (!$normalized || preg_replace('/\D/', '', $normalized) === '') {
                continue;
            }

            // Try to find existing contact (including soft-deleted to avoid constraint violation).
            $contact = Contact::withTrashed()
                ->where('user_id', $userId)
                ->where('phone', $normalized)
                ->first();

            if (!$contact) {
                $name = $dispName !== '' ? $dispName : ('WhatsApp ' . substr($number, -8));
                try {
                    $contact = Contact::create(['user_id' => $userId, 'phone' => $normalized, 'name' => $name]);
                } catch (\Throwable) {
                    $contact = Contact::withTrashed()
                        ->where('user_id', $userId)
                        ->where('phone', $normalized)
                        ->first();
                }
            } elseif ($contact->trashed()) {
                $contact->restore();
            }

            if (!$contact) {
                continue;
            }

            // Fill empty name without overwriting user's custom name.
            if (($contact->name === null || trim($contact->name) === '') && $dispName !== '') {
                $contact->name = $dispName;
                $contact->saveQuietly();
            }

            DB::table('whatsapp_conversations')
                ->where('id', $conv->id)
                ->update(['contact_id' => $contact->id]);
        }
    }

    public function down(): void
    {
        // Not reversible — contacts created here stay in the contacts table.
    }
};
