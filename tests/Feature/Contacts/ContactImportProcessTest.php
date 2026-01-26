<?php

namespace Tests\Feature\Contacts;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContactImportProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $importsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Ensure imports directory exists
        $this->importsPath = storage_path('app/imports');
        if (!file_exists($this->importsPath)) {
            mkdir($this->importsPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        $files = glob($this->importsPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    // ==========================================
    // Authorization Tests
    // ==========================================

    public function test_guest_cannot_access_import_page(): void
    {
        $response = $this->get(route('contacts.import.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_upload_file(): void
    {
        $file = $this->createSpreadsheet([['Nome'], ['João']]);

        $response = $this->post(route('contacts.import.upload'), ['file' => $file]);
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_process_import(): void
    {
        $response = $this->post(route('contacts.import.process'), [
            'columns' => [
                0 => ['index' => 0, 'header' => 'Nome', 'mapTo' => 'name', 'required' => '0'],
            ],
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_download_template(): void
    {
        $response = $this->get(route('contacts.import.template'));
        $response->assertRedirect(route('login'));
    }

    // ==========================================
    // Upload Tests
    // ==========================================

    public function test_authenticated_user_can_view_import_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('contacts.import.index'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.import.index');
    }

    public function test_can_upload_xlsx_file(): void
    {
        $file = $this->createSpreadsheet([
            ['Nome', 'Telefone', 'Email'],
            ['João Silva', '11999998888', 'joao@test.com'],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.upload'), ['file' => $file]);

        $response->assertSessionHasNoErrors();
        // Should either show mapping view (200) or redirect (302)
        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_upload_rejects_pdf_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.upload'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_rejects_large_files(): void
    {
        $file = UploadedFile::fake()->create('large.xlsx', 15000); // 15MB

        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.upload'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.upload'), []);

        $response->assertSessionHasErrors('file');
    }

    // ==========================================
    // Process Validation Tests
    // ==========================================

    public function test_process_requires_columns(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.process'), []);

        $response->assertSessionHasErrors('columns');
    }

    public function test_process_requires_at_least_one_column(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.process'), [
                'columns' => [],
            ]);

        $response->assertSessionHasErrors('columns');
    }

    public function test_process_validates_column_structure(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.process'), [
                'columns' => [
                    0 => [
                        'index' => 0,
                        // Missing mapTo and required
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_process_without_session_redirects_to_import(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.import.process'), [
                'columns' => [
                    0 => ['index' => 0, 'header' => 'Nome', 'mapTo' => 'name', 'required' => '0'],
                ],
            ]);

        $response->assertRedirect(route('contacts.import.index'));
        $response->assertSessionHas('error');
    }

    // ==========================================
    // Template Download Test
    // ==========================================

    public function test_can_download_template(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('contacts.import.template'));

        $response->assertStatus(200);
        $response->assertDownload('modelo_contatos.xlsx');
    }

    public function test_template_is_downloadable(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('contacts.import.template'));

        $response->assertStatus(200);
        $response->assertDownload();
    }

    // ==========================================
    // Contact Model Helper Tests
    // ==========================================

    public function test_contact_formats_phone_11_digits(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'phone' => '11999998888',
        ]);

        $this->assertEquals('(11)99999-8888', $contact->formatted_phone);
    }

    public function test_contact_formats_phone_10_digits(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'phone' => '1199998888',
        ]);

        $this->assertEquals('(11)9999-8888', $contact->formatted_phone);
    }

    public function test_contact_raw_phone_removes_formatting(): void
    {
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'phone' => '(11)99999-8888',
        ]);

        $this->assertEquals('11999998888', $contact->raw_phone);
    }

    // ==========================================
    // ContactField Tests for Import
    // ==========================================

    public function test_contact_field_can_store_value(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empresa',
            'type' => 'text',
        ]);

        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $contact->setFieldValue($field, 'ABC Ltda');

        $this->assertEquals('ABC Ltda', $contact->getFieldValue($field));
    }

    public function test_contact_field_updates_existing_value(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empresa',
        ]);

        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $contact->setFieldValue($field, 'Valor 1');
        $contact->setFieldValue($field, 'Valor 2');

        $this->assertEquals('Valor 2', $contact->getFieldValue($field));
        $this->assertDatabaseCount('contact_field_values', 1);
    }

    public function test_contact_field_generates_slug(): void
    {
        $field = ContactField::create([
            'user_id' => $this->user->id,
            'name' => 'Nome do Campo',
            'type' => 'text',
        ]);

        $this->assertEquals('nome-do-campo', $field->slug);
    }

    // ==========================================
    // Integration Tests (Simplified)
    // ==========================================

    public function test_can_create_contact_via_form(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.store'), [
                'name' => 'João Silva',
                'phone' => '(11)99999-8888',
                'email' => 'joao@test.com',
            ]);

        $response->assertRedirect(route('contacts.index'));
        $this->assertDatabaseHas('contacts', [
            'name' => 'João Silva',
            'email' => 'joao@test.com',
        ]);
    }

    public function test_contact_form_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.store'), [
                'phone' => '(11)99999-8888',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_contact_form_requires_phone(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.store'), [
                'name' => 'João Silva',
            ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_can_create_custom_field(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('contacts.fields.store'), [
                'name' => 'Empresa',
                'type' => 'text',
                'required' => false,
                'show_in_list' => true,
            ]);

        $response->assertRedirect(route('contacts.fields.index'));
        $this->assertDatabaseHas('contact_fields', [
            'user_id' => $this->user->id,
            'name' => 'Empresa',
        ]);
    }

    public function test_can_create_contact_with_custom_field(): void
    {
        $field = ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'CPF',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('contacts.store'), [
                'name' => 'João Silva',
                'phone' => '(11)99999-8888',
                'fields' => [
                    $field->id => '123.456.789-00',
                ],
            ]);

        $response->assertRedirect(route('contacts.index'));
        
        $contact = Contact::where('name', 'João Silva')->first();
        $this->assertEquals('123.456.789-00', $contact->getFieldValue($field));
    }

    // ==========================================
    // Data Isolation Tests
    // ==========================================

    public function test_user_only_sees_own_contacts(): void
    {
        $otherUser = User::factory()->create();
        
        Contact::factory()->create(['user_id' => $this->user->id, 'name' => 'My Contact']);
        Contact::factory()->create(['user_id' => $otherUser->id, 'name' => 'Other Contact']);

        $response = $this->actingAs($this->user)
            ->get(route('contacts.index'));

        $response->assertSee('My Contact');
        $response->assertDontSee('Other Contact');
    }

    public function test_user_only_sees_own_custom_fields(): void
    {
        $otherUser = User::factory()->create();
        
        ContactField::factory()->create(['user_id' => $this->user->id, 'name' => 'My Field']);
        ContactField::factory()->create(['user_id' => $otherUser->id, 'name' => 'Other Field']);

        $response = $this->actingAs($this->user)
            ->get(route('contacts.fields.index'));

        $response->assertSee('My Field');
        $response->assertDontSee('Other Field');
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Create a test spreadsheet file.
     */
    private function createSpreadsheet(array $data): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return new UploadedFile(
            $tempFile,
            'test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
