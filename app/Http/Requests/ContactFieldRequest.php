<?php

namespace App\Http\Requests;

use App\Models\ContactField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::in(array_keys(ContactField::TYPES))],
            'options' => ['nullable', 'string', 'required_if:type,select'],
            'required' => ['nullable', 'boolean'],
            'show_in_list' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do campo é obrigatório.',
            'type.required' => 'O tipo do campo é obrigatório.',
            'type.in' => 'Tipo de campo inválido.',
            'options.required_if' => 'As opções são obrigatórias para campos do tipo Lista.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'type' => 'tipo',
            'options' => 'opções',
            'required' => 'obrigatório',
            'show_in_list' => 'mostrar na lista',
        ];
    }
}
