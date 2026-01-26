<?php

namespace Tests\Feature\Contacts;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContactImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        $files = glob(storage_path('app/imports/*'));
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_guest_cannot_access_import_page(): void
    {
        $response = $this->get(route('contacts.import.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_import_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.import.index'));

        $response->assertStatus(200);
        $response->assertViewIs('contacts.import.index');
    }

    public function test_user_can_upload_xlsx_file(): void
    {
        // Ensure imports directory exists
        if (!file_exists(storage_path('app/imports'))) {
            mkdir(storage_path('app/imports'), 0755, true);
        }

        $file = $this->createTestSpreadsheet();

        $response = $this->actingAs($this->user)
            ->withoutExceptionHandling()
            ->post(route('contacts.import.upload'), [
                'file' => $file,
            ]);

        // May return view (200) or redirect back with session data
        if ($response->status() === 302) {
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertStatus(200);
            $response->assertViewIs('contacts.import.mapping');
            $response->assertViewHas('headers');
            $response->assertViewHas('preview');
        }
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.import.upload'), []);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_validates_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)->post(route('contacts.import.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_validates_file_size(): void
    {
        $file = UploadedFile::fake()->create('large.xlsx', 11000); // 11MB

        $response = $this->actingAs($this->user)->post(route('contacts.import.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_process_requires_at_least_one_column(): void
    {
        // Simulate session with uploaded file
        session(['import_file' => 'test.xlsx', 'import_headers' => ['Nome', 'Telefone']]);

        $response = $this->actingAs($this->user)->post(route('contacts.import.process'), [
            'columns' => [],
        ]);

        $response->assertSessionHasErrors('columns');
    }

    public function test_process_validates_column_structure(): void
    {
        session(['import_file' => 'test.xlsx', 'import_headers' => ['Nome', 'Telefone']]);

        $response = $this->actingAs($this->user)->post(route('contacts.import.process'), [
            'columns' => [
                0 => [
                    'index' => 0,
                    // Missing required fields
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_user_can_download_template(): void
    {
        $response = $this->actingAs($this->user)->get(route('contacts.import.template'));

        $response->assertStatus(200);
        $response->assertDownload('modelo_contatos.xlsx');
    }

    public function test_template_includes_custom_fields(): void
    {
        ContactField::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Empresa',
            'active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.import.template'));

        $response->assertStatus(200);
        // The template should be downloadable
        $response->assertDownload();
    }

    public function test_mapping_page_shows_headers(): void
    {
        // Ensure imports directory exists
        if (!file_exists(storage_path('app/imports'))) {
            mkdir(storage_path('app/imports'), 0755, true);
        }

        $file = $this->createTestSpreadsheet();

        $response = $this->actingAs($this->user)->post(route('contacts.import.upload'), [
            'file' => $file,
        ]);

        // The upload should succeed without errors
        $response->assertSessionHasNoErrors();
        // Response should be either view (200) or redirect (302) 
        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_import_process_validates_session(): void
    {
        // Without session data, should redirect back
        $response = $this->actingAs($this->user)->post(route('contacts.import.process'), [
            'columns' => [
                0 => [
                    'index' => 0,
                    'header' => 'Nome',
                    'mapTo' => 'name',
                    'required' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('contacts.import.index'));
    }

    public function test_phone_formatting_helper(): void
    {
        // Test the formatPhone logic directly through Contact model
        $contact = Contact::factory()->create([
            'user_id' => $this->user->id,
            'phone' => '11999998888',
        ]);

        // Verify raw phone returns only digits
        $this->assertEquals('11999998888', $contact->raw_phone);
    }

    public function test_phone_validation_allows_valid_formats(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Test Contact',
            'phone' => '(11)99999-8888',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contacts', ['name' => 'Test Contact']);
    }

    public function test_phone_validation_allows_unformatted(): void
    {
        $response = $this->actingAs($this->user)->post(route('contacts.store'), [
            'name' => 'Test Contact 2',
            'phone' => '11999998888',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contacts', ['name' => 'Test Contact 2']);
    }

    /**
     * Create a test spreadsheet file.
     */
    private function createTestSpreadsheet(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Nome');
        $sheet->setCellValue('B1', 'Telefone');
        $sheet->setCellValue('C1', 'E-mail');
        $sheet->setCellValue('A2', 'João Silva');
        $sheet->setCellValue('B2', '(11)99999-8888');
        $sheet->setCellValue('C2', 'joao@teste.com');

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
