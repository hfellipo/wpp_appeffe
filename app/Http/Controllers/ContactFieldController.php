<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFieldRequest;
use App\Models\ContactField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactFieldController extends Controller
{
    /**
     * Display a listing of custom fields.
     */
    public function index(): View
    {
        $fields = ContactField::forUser(auth()->id())
            ->ordered()
            ->get();

        return view('contacts.fields.index', compact('fields'));
    }

    /**
     * Show the form for creating a new field.
     */
    public function create(): View
    {
        $types = ContactField::TYPES;
        
        return view('contacts.fields.create', compact('types'));
    }

    /**
     * Store a newly created field.
     */
    public function store(ContactFieldRequest $request): RedirectResponse
    {
        $maxOrder = ContactField::forUser(auth()->id())->max('order') ?? 0;

        ContactField::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'type' => $request->type,
            'options' => $request->type === 'select' ? $this->parseOptions($request->options) : null,
            'required' => $request->boolean('required'),
            'show_in_list' => $request->boolean('show_in_list'),
            'order' => $maxOrder + 1,
            'active' => true,
        ]);

        return redirect()
            ->route('contacts.fields.index')
            ->with('success', 'Campo criado com sucesso!');
    }

    /**
     * Show the form for editing the specified field.
     */
    public function edit(ContactField $field): View
    {
        $this->authorizeField($field);
        
        $types = ContactField::TYPES;

        return view('contacts.fields.edit', compact('field', 'types'));
    }

    /**
     * Update the specified field.
     */
    public function update(ContactFieldRequest $request, ContactField $field): RedirectResponse
    {
        $this->authorizeField($field);

        $field->update([
            'name' => $request->name,
            'type' => $request->type,
            'options' => $request->type === 'select' ? $this->parseOptions($request->options) : null,
            'required' => $request->boolean('required'),
            'show_in_list' => $request->boolean('show_in_list'),
        ]);

        return redirect()
            ->route('contacts.fields.index')
            ->with('success', 'Campo atualizado com sucesso!');
    }

    /**
     * Toggle field active status.
     */
    public function toggle(ContactField $field): RedirectResponse
    {
        $this->authorizeField($field);

        $field->update(['active' => !$field->active]);

        $status = $field->active ? 'ativado' : 'desativado';

        return back()->with('success', "Campo {$status} com sucesso!");
    }

    /**
     * Remove the specified field.
     */
    public function destroy(ContactField $field): RedirectResponse
    {
        $this->authorizeField($field);

        $field->delete();

        return redirect()
            ->route('contacts.fields.index')
            ->with('success', 'Campo excluído com sucesso!');
    }

    /**
     * Reorder fields.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*' => 'exists:contact_fields,id',
        ]);

        foreach ($request->fields as $order => $fieldId) {
            ContactField::where('id', $fieldId)
                ->where('user_id', auth()->id())
                ->update(['order' => $order]);
        }

        return back()->with('success', 'Ordem atualizada com sucesso!');
    }

    /**
     * Parse options string into array.
     */
    private function parseOptions(?string $options): ?array
    {
        if (empty($options)) {
            return null;
        }

        return array_filter(
            array_map('trim', explode("\n", $options))
        );
    }

    /**
     * Authorize field access.
     */
    private function authorizeField(ContactField $field): void
    {
        if ($field->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
