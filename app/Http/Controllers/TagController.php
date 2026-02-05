<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = auth()->user()->accountId();

        $tags = Tag::forUser($accountId)
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $selectedTagId = $request->integer('tag');
        $selectedTag = null;
        $contacts = collect();

        if ($selectedTagId > 0) {
            $selectedTag = Tag::forUser($accountId)->find($selectedTagId);
            if ($selectedTag) {
                $contacts = $selectedTag->contacts()
                    ->orderBy('name')
                    ->get(['contacts.id', 'contacts.name', 'contacts.phone', 'contacts.email']);
            }
        }

        return view('tags.index', compact('tags', 'selectedTag', 'contacts'));
    }

    public function create(): View
    {
        return view('tags.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Tag::create([
            'user_id' => auth()->user()->accountId(),
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
        ]);

        return redirect()
            ->route('tags.index')
            ->with('success', __('Tag criada com sucesso!'));
    }

    public function edit(Tag $tag): View
    {
        $this->authorize('update', $tag);
        return view('tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $tag->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
        ]);

        return redirect()
            ->route('tags.index', ['tag' => $tag->id])
            ->with('success', __('Tag atualizada com sucesso!'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);
        $tag->delete();
        return redirect()
            ->route('tags.index')
            ->with('success', __('Tag excluída com sucesso!'));
    }

    public function editContacts(Tag $tag): View
    {
        $this->authorize('update', $tag);
        $accountId = auth()->user()->accountId();

        $currentContactIds = $tag->contacts()->pluck('contacts.id')->all();
        $contacts = Contact::forUser($accountId)->orderBy('name')->get(['id', 'name', 'phone']);

        return view('tags.contacts', compact('tag', 'contacts', 'currentContactIds'));
    }

    public function updateContacts(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);
        $accountId = auth()->user()->accountId();

        $validated = $request->validate([
            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
        ]);

        $contactIds = $validated['contact_ids'] ?? [];
        $validIds = Contact::forUser($accountId)->whereIn('id', $contactIds)->pluck('id')->all();
        $tag->contacts()->sync($validIds);

        return redirect()
            ->route('tags.index', ['tag' => $tag->id])
            ->with('success', __('Contatos da tag atualizados com sucesso!'));
    }
}
