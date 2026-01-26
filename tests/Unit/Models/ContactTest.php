<?php

namespace Tests\Unit\Models;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $contact->user);
        $this->assertEquals($user->id, $contact->user->id);
    }

    public function test_contact_has_fillable_attributes(): void
    {
        $contact = new Contact();

        $this->assertContains('user_id', $contact->getFillable());
        $this->assertContains('name', $contact->getFillable());
        $this->assertContains('phone', $contact->getFillable());
        $this->assertContains('email', $contact->getFillable());
        $this->assertContains('notes', $contact->getFillable());
    }

    public function test_formatted_phone_with_11_digits(): void
    {
        $contact = Contact::factory()->create(['phone' => '11999998888']);

        $this->assertEquals('(11)99999-8888', $contact->formatted_phone);
    }

    public function test_formatted_phone_with_10_digits(): void
    {
        $contact = Contact::factory()->create(['phone' => '1199998888']);

        $this->assertEquals('(11)9999-8888', $contact->formatted_phone);
    }

    public function test_formatted_phone_already_formatted(): void
    {
        $contact = Contact::factory()->create(['phone' => '(11)99999-8888']);

        $this->assertEquals('(11)99999-8888', $contact->formatted_phone);
    }

    public function test_raw_phone_removes_formatting(): void
    {
        $contact = Contact::factory()->create(['phone' => '(11)99999-8888']);

        $this->assertEquals('11999998888', $contact->raw_phone);
    }

    public function test_contact_has_field_values(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $field = ContactField::factory()->create(['user_id' => $user->id]);
        
        ContactFieldValue::create([
            'contact_id' => $contact->id,
            'contact_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $this->assertCount(1, $contact->fieldValues);
    }

    public function test_get_field_value_returns_value(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $field = ContactField::factory()->create(['user_id' => $user->id]);
        
        $contact->setFieldValue($field, 'Test Value');

        $this->assertEquals('Test Value', $contact->getFieldValue($field));
    }

    public function test_get_field_value_returns_null_when_not_set(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $field = ContactField::factory()->create(['user_id' => $user->id]);

        $this->assertNull($contact->getFieldValue($field));
    }

    public function test_set_field_value_creates_new_value(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $field = ContactField::factory()->create(['user_id' => $user->id]);

        $contact->setFieldValue($field, 'New Value');

        $this->assertDatabaseHas('contact_field_values', [
            'contact_id' => $contact->id,
            'contact_field_id' => $field->id,
            'value' => 'New Value',
        ]);
    }

    public function test_set_field_value_updates_existing_value(): void
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $user->id]);
        $field = ContactField::factory()->create(['user_id' => $user->id]);

        $contact->setFieldValue($field, 'Initial Value');
        $contact->setFieldValue($field, 'Updated Value');

        $this->assertDatabaseCount('contact_field_values', 1);
        $this->assertDatabaseHas('contact_field_values', [
            'contact_id' => $contact->id,
            'contact_field_id' => $field->id,
            'value' => 'Updated Value',
        ]);
    }

    public function test_scope_search_by_name(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(['user_id' => $user->id, 'name' => 'João Silva']);
        Contact::factory()->create(['user_id' => $user->id, 'name' => 'Maria Santos']);

        $results = Contact::search('João')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('João Silva', $results->first()->name);
    }

    public function test_scope_search_by_phone(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(['user_id' => $user->id, 'phone' => '(11)99999-1111']);
        Contact::factory()->create(['user_id' => $user->id, 'phone' => '(11)88888-2222']);

        $results = Contact::search('99999')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_search_by_email(): void
    {
        $user = User::factory()->create();
        Contact::factory()->create(['user_id' => $user->id, 'email' => 'joao@teste.com']);
        Contact::factory()->create(['user_id' => $user->id, 'email' => 'maria@teste.com']);

        $results = Contact::search('joao@')->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_for_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Contact::factory()->count(3)->create(['user_id' => $user1->id]);
        Contact::factory()->count(2)->create(['user_id' => $user2->id]);

        $results = Contact::forUser($user1->id)->get();

        $this->assertCount(3, $results);
    }

    public function test_contact_can_be_soft_deleted(): void
    {
        $contact = Contact::factory()->create();

        $contact->delete();

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }
}
