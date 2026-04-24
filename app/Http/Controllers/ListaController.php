<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Lista;
use App\Models\WhatsAppContact;
use App\Services\AutomationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListaController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = auth()->user()->accountId();

        $listas = Lista::forUser($accountId)
            ->withCount(['contacts', 'whatsappContacts'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('listas.index', compact('listas'));
    }

    public function create(): View
    {
        return view('listas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Lista::create([
            'user_id' => auth()->user()->accountId(),
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('listas.index')
            ->with('success', __('Lista criada com sucesso!'));
    }

    public function show(Lista $lista): View
    {
        $this->authorize('view', $lista);

        $lista->load(['contacts', 'whatsappContacts']);

        return view('listas.show', compact('lista'));
    }

    public function edit(Lista $lista): View
    {
        $this->authorize('update', $lista);

        return view('listas.edit', compact('lista'));
    }

    public function update(Request $request, Lista $lista): RedirectResponse
    {
        $this->authorize('update', $lista);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $lista->update(['name' => $validated['name']]);

        return redirect()
            ->route('listas.show', $lista)
            ->with('success', __('Lista atualizada com sucesso!'));
    }

    public function destroy(Lista $lista): RedirectResponse
    {
        $this->authorize('delete', $lista);

        $lista->delete();

        return redirect()
            ->route('listas.index')
            ->with('success', __('Lista excluída com sucesso!'));
    }

    /**
     * Show form to add contacts (from contacts and whatsapp_contacts) to the list.
     */
    public function editContacts(Lista $lista): View
    {
        $this->authorize('update', $lista);

        $accountId = auth()->user()->accountId();

        $currentContactIds = $lista->contacts()->pluck('contacts.id')->all();
        $currentWaIds = $lista->whatsappContacts()->pluck('whatsapp_contacts.id')->all();

        $contacts = Contact::forUser($accountId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $whatsappContacts = WhatsAppContact::where('user_id', $accountId)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'contact_number', 'contact_jid']);

        return view('listas.contacts', compact('lista', 'contacts', 'whatsappContacts', 'currentContactIds', 'currentWaIds'));
    }

    /**
     * Attach/detach contacts to the list. Request can include contact_ids and whatsapp_contact_ids to attach,
     * and detach_contact_ids / detach_whatsapp_contact_ids to remove.
     * We replace the entire set: we send current selection and save as sync.
     */
    public function updateContacts(Request $request, Lista $lista): RedirectResponse
    {
        $this->authorize('update', $lista);

        $accountId = auth()->user()->accountId();

        $validated = $request->validate([
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
            'whatsapp_contact_ids' => ['nullable', 'array'],
            'whatsapp_contact_ids.*' => ['integer', 'exists:whatsapp_contacts,id'],
        ]);

        $contactIds = $validated['contact_ids'] ?? [];
        $waIds = $validated['whatsapp_contact_ids'] ?? [];

        // Ensure all belong to account
        $validContactIds = Contact::forUser($accountId)->whereIn('id', $contactIds)->pluck('id')->all();
        $validWaIds = WhatsAppContact::where('user_id', $accountId)->whereIn('id', $waIds)->pluck('id')->all();

        $previousIds = $lista->contacts()->pluck('contacts.id')->flip();

        $lista->contacts()->sync($validContactIds);
        $lista->whatsappContacts()->sync($validWaIds);

        // Trigger automations for newly added contacts
        $eventService = app(AutomationEventService::class);
        foreach ($validContactIds as $contactId) {
            if (! $previousIds->has($contactId)) {
                $contact = Contact::find($contactId);
                if ($contact) {
                    $eventService->contactAddedToList($contact, $lista->id);
                }
            }
        }

        return redirect()
            ->route('listas.show', $lista)
            ->with('success', __('Contatos da lista atualizados com sucesso!'));
    }

    /**
     * Remove one contact (app contact) from the list.
     */
    public function detachContact(Request $request, Lista $lista): RedirectResponse
    {
        $this->authorize('update', $lista);

        $request->validate(['contact_id' => ['required', 'integer', 'exists:contacts,id']]);

        $contact = Contact::findOrFail($request->contact_id);
        if ((int) $contact->user_id !== (int) auth()->user()->accountId()) {
            abort(403);
        }

        $lista->contacts()->detach($contact->id);

        return redirect()
            ->route('listas.show', $lista)
            ->with('success', __('Contato removido da lista.'));
    }

    /**
     * Remove one WhatsApp contact from the list.
     */
    public function detachWhatsAppContact(Request $request, Lista $lista): RedirectResponse
    {
        $this->authorize('update', $lista);

        $request->validate(['whatsapp_contact_id' => ['required', 'integer', 'exists:whatsapp_contacts,id']]);

        $wa = WhatsAppContact::findOrFail($request->whatsapp_contact_id);
        if ((int) $wa->user_id !== (int) auth()->user()->accountId()) {
            abort(403);
        }

        $lista->whatsappContacts()->detach($wa->id);

        return redirect()
            ->route('listas.show', $lista)
            ->with('success', __('Contato WhatsApp removido da lista.'));
    }
}
