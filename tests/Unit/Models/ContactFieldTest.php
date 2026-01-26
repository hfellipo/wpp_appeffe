<?php

namespace Tests\Unit\Models;

use App\Models\ContactField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_field_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $field = ContactField::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $field->user);
        $this->assertEquals($user->id, $field->user->id);
    }

    public function test_contact_field_has_fillable_attributes(): void
    {
        $field = new ContactField();

        $this->assertContains('user_id', $field->getFillable());
        $this->assertContains('name', $field->getFillable());
        $this->assertContains('slug', $field->getFillable());
        $this->assertContains('type', $field->getFillable());
        $this->assertContains('options', $field->getFillable());
        $this->assertContains('required', $field->getFillable());
        $this->assertContains('show_in_list', $field->getFillable());
        $this->assertContains('order', $field->getFillable());
        $this->assertContains('active', $field->getFillable());
    }

    public function test_options_is_cast_to_array(): void
    {
        $field = ContactField::factory()->create([
            'type' => 'select',
            'options' => ['Option 1', 'Option 2', 'Option 3'],
        ]);

        $this->assertIsArray($field->options);
        $this->assertCount(3, $field->options);
    }

    public function test_required_is_cast_to_boolean(): void
    {
        $field = ContactField::factory()->create(['required' => 1]);

        $this->assertIsBool($field->required);
        $this->assertTrue($field->required);
    }

    public function test_show_in_list_is_cast_to_boolean(): void
    {
        $field = ContactField::factory()->create(['show_in_list' => 0]);

        $this->assertIsBool($field->show_in_list);
        $this->assertFalse($field->show_in_list);
    }

    public function test_active_is_cast_to_boolean(): void
    {
        $field = ContactField::factory()->create(['active' => true]);

        $this->assertIsBool($field->active);
        $this->assertTrue($field->active);
    }

    public function test_slug_is_generated_on_create(): void
    {
        $user = User::factory()->create();
        $field = ContactField::create([
            'user_id' => $user->id,
            'name' => 'Empresa do Cliente',
            'type' => 'text',
        ]);

        $this->assertEquals('empresa-do-cliente', $field->slug);
    }

    public function test_type_label_returns_correct_label(): void
    {
        $field = ContactField::factory()->create(['type' => 'text']);
        $this->assertEquals('Texto', $field->type_label);

        $field = ContactField::factory()->create(['type' => 'date']);
        $this->assertEquals('Data', $field->type_label);

        $field = ContactField::factory()->create(['type' => 'select']);
        $this->assertEquals('Lista de Opções', $field->type_label);
    }

    public function test_types_constant_has_all_types(): void
    {
        $types = ContactField::TYPES;

        $this->assertArrayHasKey('text', $types);
        $this->assertArrayHasKey('number', $types);
        $this->assertArrayHasKey('date', $types);
        $this->assertArrayHasKey('email', $types);
        $this->assertArrayHasKey('url', $types);
        $this->assertArrayHasKey('textarea', $types);
        $this->assertArrayHasKey('select', $types);
    }

    public function test_scope_active_returns_only_active_fields(): void
    {
        $user = User::factory()->create();
        ContactField::factory()->count(3)->create(['user_id' => $user->id, 'active' => true]);
        ContactField::factory()->count(2)->create(['user_id' => $user->id, 'active' => false]);

        $results = ContactField::forUser($user->id)->active()->get();

        $this->assertCount(3, $results);
    }

    public function test_scope_show_in_list_returns_visible_fields(): void
    {
        $user = User::factory()->create();
        ContactField::factory()->count(2)->create(['user_id' => $user->id, 'show_in_list' => true]);
        ContactField::factory()->count(3)->create(['user_id' => $user->id, 'show_in_list' => false]);

        $results = ContactField::forUser($user->id)->showInList()->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_ordered_returns_fields_by_order(): void
    {
        $user = User::factory()->create();
        ContactField::factory()->create(['user_id' => $user->id, 'name' => 'Field C', 'order' => 3]);
        ContactField::factory()->create(['user_id' => $user->id, 'name' => 'Field A', 'order' => 1]);
        ContactField::factory()->create(['user_id' => $user->id, 'name' => 'Field B', 'order' => 2]);

        $results = ContactField::forUser($user->id)->ordered()->get();

        $this->assertEquals('Field A', $results[0]->name);
        $this->assertEquals('Field B', $results[1]->name);
        $this->assertEquals('Field C', $results[2]->name);
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        ContactField::factory()->count(3)->create(['user_id' => $user1->id]);
        ContactField::factory()->count(2)->create(['user_id' => $user2->id]);

        $results = ContactField::forUser($user1->id)->get();

        $this->assertCount(3, $results);
    }
}
