<?php

namespace Tests\Feature\Contacts;

use App\Models\ContactField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_contact_fields(): void
    {
        $response = $this->get(route('contacts.fields.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_fields_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.fields.index'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.fields.index');
    }

    public function test_user_sees_only_own_fields(): void
    {
        $otherUser = User::factory()->create();
        
        ContactField::factory()->create(['user_id' => $this->user->id, 'name' => 'My Field']);
        ContactField::factory()->create(['user_id' => $otherUser->id, 'name' => 'Other Field']);

        $response = $this->actingAs($this->user)->get(route('contacts.fields.index'));

        $response->assertSee('My Field');
        $response->assertDontSee('Other Field');
    }

    public function test_user_can_view_create_field_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.fields.create'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.fields.create');
        $response->assertViewHas('types');
    }

    public function test_user_can_create_text_field(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'name' => 'Empresa',
            'type' => 'text',
            'required' => false,
            'show_in_list' => true,
        ]);

        $response->assertRedirect(route('contacts.fields.index'));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('contact_fields', [
            'user_id' => $this->user->id,
            'name' => 'Empresa',
            'type' => 'text',
            'slug' => 'empresa',
        ]);
    }

    public function test_user_can_create_select_field_with_options(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'name' => 'Categoria',
            'type' => 'select',
            'options' => "Cliente\nFornecedor\nParceiro",
            'required' => true,
            'show_in_list' => true,
        ]);

        $response->assertRedirect(route('contacts.fields.index'));
        
        $field = ContactField::where('name', 'Categoria')->first();
        $this->assertEquals(['Cliente', 'Fornecedor', 'Parceiro'], $field->options);
    }

    public function test_select_field_requires_options(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'name' => 'Categoria',
            'type' => 'select',
            'options' => '',
        ]);

        $response->assertSessionHasErrors('options');
    }

    public function test_field_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'type' => 'text',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_field_creation_requires_valid_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'name' => 'Campo',
            'type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_user_can_view_edit_field_form(): void
    {
        $field = ContactField::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('contacts.fields.edit', $field));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.fields.edit');
        $response->assertViewHas('field', $field);
    }

    public function test_user_cannot_edit_other_users_field(): void
    {
        $otherUser = User::factory()->create();
        $field = ContactField::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('contacts.fields.edit', $field));

        $response->assertStatus(403);
    }

    public function test_user_can_update_field(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Nome Antigo',
        ]);

        $response = $this->actingAs($this->user)->put(route('contacts.fields.update', $field), [
            'name' => 'Nome Novo',
            'type' => 'text',
            'required' => true,
            'show_in_list' => false,
        ]);

        $response->assertRedirect(route('contacts.fields.index'));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('contact_fields', [
            'id' => $field->id,
            'name' => 'Nome Novo',
            'required' => true,
            'show_in_list' => false,
        ]);
    }

    public function test_user_cannot_update_other_users_field(): void
    {
        $otherUser = User::factory()->create();
        $field = ContactField::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->put(route('contacts.fields.update', $field), [
            'name' => 'Tentativa',
            'type' => 'text',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_toggle_field_status(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
        ]);

        $response = $this->actingAs($this->user)->patch(route('contacts.fields.toggle', $field));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertFalse($field->fresh()->active);
    }

    public function test_user_cannot_toggle_other_users_field(): void
    {
        $otherUser = User::factory()->create();
        $field = ContactField::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->patch(route('contacts.fields.toggle', $field));

        $response->assertStatus(403);
    }

    public function test_user_can_delete_field(): void
    {
        $field = ContactField::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('contacts.fields.destroy', $field));

        $response->assertRedirect(route('contacts.fields.index'));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseMissing('contact_fields', ['id' => $field->id]);
    }

    public function test_user_cannot_delete_other_users_field(): void
    {
        $otherUser = User::factory()->create();
        $field = ContactField::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->delete(route('contacts.fields.destroy', $field));

        $response->assertStatus(403);
    }

    public function test_fields_are_created_with_incremental_order(): void
    {
        ContactField::factory()->create(['user_id' => $this->user->id, 'order' => 5]);

        $this->actingAs($this->user)->post(route('contacts.fields.store'), [
            'name' => 'Novo Campo',
            'type' => 'text',
        ]);

        $newField = ContactField::where('name', 'Novo Campo')->first();
        $this->assertEquals(6, $newField->order);
    }

    public function test_all_field_types_can_be_created(): void
    {
        $types = ['text', 'number', 'date', 'email', 'url', 'textarea'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->post(route('contacts.fields.store'), [
                'name' => "Campo {$type}",
                'type' => $type,
            ]);

            $response->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('contact_fields', count($types));
    }
}
