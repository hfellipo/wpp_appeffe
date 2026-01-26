<?php

namespace Tests\Feature\Contacts;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_contacts(): void
    {
        $response = $this->get(route('contacts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_contacts_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.index'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.index');
    }

    public function test_user_sees_only_own_contacts(): void
    {
        $otherUser = User::factory()->create();
        
        Contact::factory()->create(['user_id' => $this->user->id, 'name' => 'My Contact']);
        Contact::factory()->create(['user_id' => $otherUser->id, 'name' => 'Other Contact']);

        $response = $this->actingAs($this->user)->get(route('contacts.index'));

        $response->assertSee('My Contact');
        $response->assertDontSee('Other Contact');
    }

    public function test_user_can_search_contacts(): void
    {
        Contact::factory()->create(['user_id' => $this->user->id, 'name' => 'João Silva']);
        Contact::factory()->create(['user_id' => $this->user->id, 'name' => 'Maria Santos']);

        $response = $this->actingAs($this->user)->get(route('contacts.index', ['search' => 'João']));

        $response->assertSee('João Silva');
        $response->assertDontSee('Maria Santos');
    }

    public function test_user_can_view_create_contact_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.create'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.create');
    }

    public function test_user_can_create_contact(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Novo Contato',
            'phone' => '(11)99999-8888',
            'email' => 'novo@contato.com',
            'notes' => 'Observações do contato',
        ]);

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Novo Contato',
            'email' => 'novo@contato.com',
        ]);
    }

    public function test_contact_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'phone' => '(11)99999-8888',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_contact_creation_requires_phone(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Novo Contato',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_phone_must_have_valid_format(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Novo Contato',
            'phone' => 'abc',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_user_can_create_contact_with_custom_fields(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empresa',
        ]);

        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Contato Empresa',
            'phone' => '(11)99999-7777',
            'fields' => [
                $field->id => 'Empresa XYZ',
            ],
        ]);

        $response->assertRedirect(route('contacts.index'));
        
        $contact = Contact::where('name', 'Contato Empresa')->first();
        $this->assertEquals('Empresa XYZ', $contact->getFieldValue($field));
    }

    public function test_user_can_view_edit_contact_form(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('contacts.edit', $contact));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.edit');
        $response->assertViewHas('contact', $contact);
    }

    public function test_user_cannot_edit_other_users_contact(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('contacts.edit', $contact));

        $response->assertStatus(403);
    }

    public function test_user_can_update_contact(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Nome Antigo',
        ]);

        $response = $this->actingAs($this->user)->put(route('contacts.update', $contact), [
            'name' => 'Nome Novo',
            'phone' => '(11)88888-7777',
        ]);

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_user_cannot_update_other_users_contact(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->put(route('contacts.update', $contact), [
            'name' => 'Tentativa de Alteração',
            'phone' => '(11)99999-0000',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_contact(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));
        $response->assertSessionHas('success');
        
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_delete_other_users_contact(): void
    {
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->delete(route('contacts.destroy', $contact));

        $response->assertStatus(403);
    }

    public function test_contacts_are_paginated(): void
    {
        Contact::factory()->count(20)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('contacts.index'));

        $response->assertViewHas('contacts', function ($contacts) {
            return $contacts->count() === 15; // Default pagination
        });
    }

    public function test_custom_fields_are_shown_in_list(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empresa',
            'show_in_list' => true,
            'active' => true,
        ]);

        $contact = Contact::factory()->create(['user_id' => $this->user->id]);
        $contact->setFieldValue($field, 'Empresa ABC');

        $response = $this->actingAs($this->user)->get(route('contacts.index'));

        $response->assertSee('Empresa');
        $response->assertSee('Empresa ABC');
    }
}
