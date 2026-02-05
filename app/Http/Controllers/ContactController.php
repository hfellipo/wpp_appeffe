<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\AutomationRun;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\Tag;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): View
    {
        $accountId = auth()->user()->accountId();

        $contacts = Contact::forUser($accountId)
            ->with('fieldValues.field')
            ->search($request->search)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $customFields = ContactField::forUser($accountId)
            ->active()
            ->showInList()
            ->ordered()
            ->get();

        return view('contacts.index', compact('contacts', 'customFields'));
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(): View
    {
        $customFields = ContactField::forUser(auth()->user()->accountId())
            ->active()
            ->ordered()
            ->get();

        return view('contacts.create', compact('customFields'));
    }

    /**
     * Store a newly created contact.
     */
    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::create([
            'user_id' => auth()->user()->accountId(),
            'name' => $request->name,
            'phone' => $this->formatPhoneForStorage($request->phone),
            'email' => $request->email,
            'notes' => $request->notes,
        ]);

        // Save custom field values
        if ($request->has('fields')) {
            foreach ($request->fields as $fieldId => $value) {
                if (!empty($value)) {
                    $contact->setFieldValue($fieldId, $value);
                }
            }
        }

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Contato criado com sucesso!');
    }

    /**
     * Display the specified contact (all data, listas, tags, event history).
     */
    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $contact->load('fieldValues.field', 'listas', 'tags');

        $customFields = ContactField::forUser(auth()->user()->accountId())
            ->active()
            ->ordered()
            ->get();

        $accountId = auth()->user()->accountId();
        $events = $this->buildContactEventHistory($contact, $accountId);

        return view('contacts.show', compact('contact', 'customFields', 'events'));
    }

    /**
     * Build unified event timeline for contact: WhatsApp messages + automation runs.
     */
    private function buildContactEventHistory(Contact $contact, int $accountId): Collection
    {
        $phoneNorm = $contact->phone_for_whatsapp;
        $peerJid = $phoneNorm !== '' ? $phoneNorm . '@s.whatsapp.net' : null;

        $conversationIds = WhatsAppConversation::query()
            ->where('user_id', $accountId)
            ->where(function ($q) use ($contact, $peerJid) {
                $q->where('contact_id', $contact->id);
                if ($peerJid !== null) {
                    $q->orWhere('peer_jid', $peerJid);
                }
            })
            ->pluck('id');

        $messages = collect();
        if ($conversationIds->isNotEmpty()) {
            $messages = WhatsAppMessage::query()
                ->whereIn('conversation_id', $conversationIds)
                ->with(['conversation', 'automationRun.automation'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn (WhatsAppMessage $m) => [
                    'type' => 'message',
                    'at' => $m->sent_at ?? $m->created_at,
                    'message' => $m,
                ]);
        }

        $runs = AutomationRun::query()
            ->where('contact_id', $contact->id)
            ->with('automation')
            ->orderByDesc('ran_at')
            ->limit(100)
            ->get()
            ->map(fn (AutomationRun $r) => [
                'type' => 'automation_run',
                'at' => $r->ran_at,
                'run' => $r,
            ]);

        $events = $messages->concat($runs)
            ->sortByDesc(fn ($e) => $e['at']->getTimestamp())
            ->values()
            ->take(50);

        return $events;
    }

    /**
     * Show the form for editing the specified contact.
     */
    public function edit(Contact $contact): View
    {
        $this->authorize('update', $contact);

        $contact->load('fieldValues', 'tags');

        $customFields = ContactField::forUser(auth()->user()->accountId())
            ->active()
            ->ordered()
            ->get();

        $tags = Tag::forUser(auth()->user()->accountId())->orderBy('name')->get(['id', 'name', 'color']);

        return view('contacts.edit', compact('contact', 'customFields', 'tags'));
    }

    /**
     * Update the specified contact.
     */
    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $contact->update([
            'name' => $request->name,
            'phone' => $this->formatPhoneForStorage($request->phone),
            'email' => $request->email,
            'notes' => $request->notes,
        ]);

        // Update custom field values
        if ($request->has('fields')) {
            foreach ($request->fields as $fieldId => $value) {
                $contact->setFieldValue($fieldId, $value ?: null);
            }
        }

        // Update tags
        $tagIds = $request->input('tag_ids', []);
        $validTagIds = Tag::forUser(auth()->user()->accountId())->whereIn('id', $tagIds)->pluck('id')->all();
        $contact->tags()->sync($validTagIds);

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Contato atualizado com sucesso!');
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Contato excluído com sucesso!');
    }

    /**
     * Format phone number for storage: (XX)XXXXX-XXXX ou (XX)XXXX-XXXX.
     * Remove zero à esquerda (ex: 031994234090) para formato consistente e uso no WhatsApp.
     */
    private function formatPhoneForStorage(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 11) {
            return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }
        return $phone;
    }
}
