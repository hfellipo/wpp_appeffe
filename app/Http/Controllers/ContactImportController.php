<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactField;
use App\Models\Lista;
use App\Services\AutomationEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ContactImportController extends Controller
{
    /**
     * Show the import form.
     */
    public function index(): View
    {
        $listas = Lista::forUser(auth()->user()->accountId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('contacts.import.index', compact('listas'));
    }

    /**
     * Upload and preview the file.
     */
    public function upload(Request $request): View|RedirectResponse
    {
        $rules = [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240', // 10MB max
            'lista_option' => 'nullable|in:none,existing,new',
            'lista_id' => 'nullable|required_if:lista_option,existing|integer|exists:listas,id',
            'new_list_name' => 'nullable|required_if:lista_option,new|string|max:255',
        ];
        $validated = $request->validate($rules);

        // Ensure lista_id belongs to user's account when "existing"
        if (($validated['lista_option'] ?? '') === 'existing' && !empty($validated['lista_id'] ?? null)) {
            $exists = Lista::forUser(auth()->user()->accountId())->where('id', $validated['lista_id'])->exists();
            if (!$exists) {
                return back()->withErrors(['lista_id' => __('A lista selecionada não é válida.')])->withInput();
            }
        }

        // Ensure imports directory exists
        $importsPath = storage_path('app/imports');
        if (!file_exists($importsPath)) {
            if (!mkdir($importsPath, 0755, true)) {
                return back()->with('error', 'Não foi possível criar o diretório de importação. Verifique as permissões.');
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($importsPath)) {
            return back()->with('error', 'O diretório de importação não tem permissão de escrita. Verifique as permissões.');
        }

        $file = $request->file('file');
        
        try {
            // Store in imports directory using imports disk with unique name
            // Include session ID to ensure uniqueness and traceability
            $sessionId = substr(session()->getId(), 0, 8);
            $filename = 'import_' . $sessionId . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('', $filename, 'imports');
            
            // Verify file was saved using Storage facade
            if (!Storage::disk('imports')->exists($path)) {
                Log::error('Arquivo não encontrado após upload', [
                    'path' => $path,
                    'storage_path' => Storage::disk('imports')->path($path),
                ]);
                return back()->with('error', 'Erro ao salvar o arquivo. O arquivo não foi encontrado após o upload.');
            }
            
            // Get full path using Storage facade
            $fullPath = Storage::disk('imports')->path($path);
            
        } catch (\Exception $e) {
            Log::error('Erro ao salvar arquivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Erro ao salvar o arquivo: ' . $e->getMessage());
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                Storage::disk('imports')->delete($path);
                return back()->with('error', 'O arquivo está vazio.');
            }

            // Get headers (first row)
            $headers = array_shift($rows);
            $headers = array_map(fn($h) => trim($h ?? ''), $headers);

            // Get preview data (first 5 rows)
            $preview = array_slice($rows, 0, 5);

            // Get user's custom fields
            $customFields = ContactField::forUser(auth()->user()->accountId())
                ->active()
                ->ordered()
                ->get();

            // Store file path and optional list assignment in session
            session()->put('import_file', $path);
            session()->put('import_headers', $headers);
            $listaOption = $validated['lista_option'] ?? 'none';
            session()->put('import_lista_option', $listaOption);
            if ($listaOption === 'existing' && !empty($validated['lista_id'] ?? null)) {
                session()->put('import_lista_id', (int) $validated['lista_id']);
                session()->forget('import_new_list_name');
            } elseif ($listaOption === 'new' && !empty(trim($validated['new_list_name'] ?? ''))) {
                session()->put('import_new_list_name', trim($validated['new_list_name']));
                session()->forget('import_lista_id');
            } else {
                session()->forget(['import_lista_id', 'import_new_list_name']);
            }
            session()->save(); // Force save session
            
            Log::info('Arquivo salvo na sessão', [
                'path' => $path,
                'session_id' => session()->getId(),
                'headers_count' => count($headers),
            ]);

            return view('contacts.import.mapping', compact('headers', 'preview', 'customFields'));

        } catch (\Exception $e) {
            if (isset($path) && Storage::disk('imports')->exists($path)) {
                Storage::disk('imports')->delete($path);
            }
            
            Log::error('Erro ao ler arquivo de importação', [
                'error' => $e->getMessage(),
                'path' => $path ?? 'unknown',
            ]);
            
            $errorMessage = 'Erro ao ler o arquivo.';
            if (config('app.debug')) {
                $errorMessage .= ' ' . $e->getMessage();
            }
            
            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Process the import with field mapping (initiates async processing).
     */
    public function process(Request $request): View|RedirectResponse
    {
        $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*.index' => 'required|integer|min:0',
            'columns.*.header' => 'nullable|string',
            'columns.*.mapTo' => 'required|string',
            'columns.*.required' => 'required|in:0,1',
        ], [
            'columns.required' => 'Selecione pelo menos uma coluna para importar.',
            'columns.min' => 'Selecione pelo menos uma coluna para importar.',
        ]);

        // Try to get path from session first, then from request (fallback)
        $path = session('import_file') ?? $request->input('import_file');
        
        // Get headers from session or request
        $headers = session('import_headers');
        if (empty($headers) && $request->has('import_headers')) {
            $headersJson = $request->input('import_headers');
            $decodedHeaders = json_decode($headersJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedHeaders)) {
                $headers = $decodedHeaders;
            } else {
                $headers = [];
            }
        }
        
        // Ensure headers is an array
        if (!is_array($headers)) {
            $headers = [];
        }

        if (!$path) {
            Log::warning('Sessão de importação vazia', [
                'session_id' => session()->getId(),
                'all_session_keys' => array_keys(session()->all()),
                'has_import_file' => session()->has('import_file'),
                'has_import_headers' => session()->has('import_headers'),
                'request_has_file' => $request->has('import_file'),
                'request_file_value' => $request->input('import_file'),
            ]);
            
            return redirect()
                ->route('contacts.import.index')
                ->with('error', 'Arquivo não encontrado na sessão. Por favor, faça o upload novamente. Se o problema persistir, verifique se os cookies estão habilitados.');
        }
        
        // If we got headers from request, restore them to session
        if (!session()->has('import_headers') && !empty($headers)) {
            session()->put('import_headers', $headers);
            session()->save();
        }
        
        // If we got path from request, restore it to session
        if (!session()->has('import_file') && $path) {
            session()->put('import_file', $path);
            session()->save();
        }
        
        Log::info('Processando importação', [
            'path' => $path,
            'session_id' => session()->getId(),
            'path_from_session' => session()->has('import_file'),
            'path_from_request' => $request->has('import_file'),
            'headers_count' => is_array($headers) ? count($headers) : 0,
        ]);

        // Verify file exists using Storage facade
        if (!Storage::disk('imports')->exists($path)) {
            Log::error('Arquivo não encontrado no processamento', [
                'path' => $path,
                'session_path' => session('import_file'),
            ]);
            return redirect()
                ->route('contacts.import.index')
                ->with('error', 'Arquivo não encontrado no servidor. Por favor, faça o upload novamente.');
        }

        // Store columns mapping in session for chunk processing
        session()->put('import_columns', json_encode($request->columns));
        session()->put('import_cancelled', false);
        session()->save();

        // Get total rows count
        try {
            $fullPath = Storage::disk('imports')->path($path);
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            $totalRows = count($rows) - 1; // Exclude header
        } catch (\Exception $e) {
            return redirect()
                ->route('contacts.import.index')
                ->with('error', 'Erro ao ler o arquivo: ' . $e->getMessage());
        }

        // Return progress view
        return view('contacts.import.progress', [
            'totalRows' => $totalRows,
            'path' => $path,
        ]);
    }

    /**
     * Create new fields for columns mapped as "new".
     */
    private function createNewFields(array $columns): array
    {
        $newFields = [];
        $accountId = auth()->user()->accountId();
        $maxOrder = ContactField::forUser($accountId)->max('order') ?? 0;

        foreach ($columns as $colIndex => $config) {
            if ($config['mapTo'] === 'new') {
                $fieldName = !empty($config['header']) ? $config['header'] : "Campo {$colIndex}";
                
                // Check if field with same name already exists
                $existingField = ContactField::forUser($accountId)
                    ->where('name', $fieldName)
                    ->first();

                if ($existingField) {
                    $newFields[$colIndex] = $existingField->id;
                } else {
                    $maxOrder++;
                    $field = ContactField::create([
                        'user_id' => $accountId,
                        'name' => $fieldName,
                        'slug' => Str::slug($fieldName),
                        'type' => 'text',
                        'required' => $config['required'] == '1',
                        'show_in_list' => true,
                        'order' => $maxOrder,
                        'active' => true,
                    ]);
                    $newFields[$colIndex] = $field->id;
                }
            }
        }

        return $newFields;
    }

    /**
     * Build the mapping structure for processing.
     */
    private function buildMapping(array $columns, array $newFields): array
    {
        $mapping = [];

        foreach ($columns as $colIndex => $config) {
            $mapTo = $config['mapTo'];
            $colIndexInt = (int) $config['index'];

            $entry = [
                'header' => $config['header'] ?? '',
                'required' => $config['required'] == '1',
            ];

            if ($mapTo === 'new') {
                $entry['type'] = 'field';
                $entry['field_id'] = $newFields[$colIndex];
            } elseif (in_array($mapTo, ['name', 'phone', 'email', 'notes'])) {
                $entry['type'] = $mapTo;
            } elseif (str_starts_with($mapTo, 'field_')) {
                $entry['type'] = 'field';
                $entry['field_id'] = (int) str_replace('field_', '', $mapTo);
            }

            $mapping[$colIndexInt] = $entry;
        }

        return $mapping;
    }

    /**
     * Download a sample template.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Default headers
        $headers = ['Nome', 'Telefone', 'E-mail', 'Observações'];

        // Add custom field headers
        $customFields = ContactField::forUser(auth()->user()->accountId())
            ->active()
            ->ordered()
            ->get();

        foreach ($customFields as $field) {
            $headers[] = $field->name;
        }

        // Set headers
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Add example row
        $sheet->setCellValueByColumnAndRow(1, 2, 'João Silva');
        $sheet->setCellValueByColumnAndRow(2, 2, '(11)99999-9999');
        $sheet->setCellValueByColumnAndRow(3, 2, 'joao@exemplo.com');
        $sheet->setCellValueByColumnAndRow(4, 2, 'Cliente VIP');

        // Create file
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = 'modelo_contatos.xlsx';
        $tempPath = storage_path('app/temp/' . $filename);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Format phone number — delegates to Contact::normalizePhoneForStorage() for consistency.
     */
    private function formatPhone(string $phone): string
    {
        return Contact::normalizePhoneForStorage($phone) ?: $phone;
    }

    /**
     * Validate phone number format.
     */
    private function isValidPhone(string $phone): bool
    {
        // Allow placeholder phone
        if ($phone === '(00)00000-0000') {
            return true;
        }

        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 10 && strlen($digits) <= 11;
    }

    /**
     * Process chunk of rows (AJAX endpoint).
     */
    public function processChunk(Request $request)
    {
        set_time_limit(60); // 1 minute per chunk
        
        $chunk = (int) $request->input('chunk', 0);
        $chunkSize = 50; // Process 50 rows at a time
        $path = session('import_file');
        $columns = json_decode(session('import_columns'), true);
        
        if (!$path || !$columns) {
            return response()->json([
                'error' => 'Dados de importação não encontrados.',
                'completed' => true,
            ], 400);
        }

        // Check if cancelled
        if (session('import_cancelled', false)) {
            return response()->json([
                'cancelled' => true,
                'completed' => true,
            ]);
        }

        try {
            $fullPath = Storage::disk('imports')->path($path);
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            array_shift($rows); // Remove header

            $totalRows = count($rows);
            $start = $chunk * $chunkSize;
            $end = min($start + $chunkSize, $totalRows);
            $chunkRows = array_slice($rows, $start, $chunkSize);

            if (empty($chunkRows)) {
                // Clean up
                Storage::disk('imports')->delete($path);
                session()->forget([
                    'import_file', 'import_headers', 'import_columns', 'import_stats', 'import_cancelled',
                    'import_lista_option', 'import_lista_id', 'import_new_list_name', 'import_existing_contacts', 'import_mapping',
                ]);
                
                $stats = session('import_stats', ['imported' => 0, 'updated' => 0, 'skipped' => 0]);
                
                return response()->json([
                    'completed' => true,
                    'stats' => $stats,
                ]);
            }

            // Load existing contacts once (store as array of IDs keyed by normalized phone)
            if ($chunk === 0) {
                $existingContactsData = Contact::where('user_id', auth()->user()->accountId())
                    ->get()
                    ->mapWithKeys(function ($contact) {
                        $normalizedPhone = preg_replace('/\D/', '', $contact->phone);
                        return [$normalizedPhone => $contact->id];
                    })
                    ->toArray();
                session()->put('import_existing_contacts', $existingContactsData);
                $existingContacts = Contact::whereIn('id', array_values($existingContactsData))
                    ->get()
                    ->keyBy(function ($contact) {
                        return preg_replace('/\D/', '', $contact->phone);
                    });
            } else {
                $existingContactsIds = session('import_existing_contacts', []);
                $existingContacts = Contact::whereIn('id', array_values($existingContactsIds))
                    ->get()
                    ->keyBy(function ($contact) {
                        return preg_replace('/\D/', '', $contact->phone);
                    });
            }

            // Create fields and build mapping (only on first chunk)
            if ($chunk === 0) {
                $newFields = $this->createNewFields($columns);
                $mapping = $this->buildMapping($columns, $newFields);
                session()->put('import_mapping', $mapping);
                session()->put('import_stats', ['imported' => 0, 'updated' => 0, 'skipped' => 0]);

                // Resolve list for attaching imported contacts
                $listaOption = session('import_lista_option', 'none');
                if ($listaOption === 'existing' && session()->has('import_lista_id')) {
                    // already have list id
                } elseif ($listaOption === 'new' && session()->has('import_new_list_name')) {
                    $newListName = trim(session('import_new_list_name'));
                    if ($newListName !== '') {
                        $lista = Lista::create([
                            'user_id' => auth()->user()->accountId(),
                            'name' => $newListName,
                        ]);
                        session()->put('import_lista_id', $lista->id);
                        session()->forget('import_new_list_name');
                    }
                }
            }

            $listId = null;
            if (in_array(session('import_lista_option', 'none'), ['existing', 'new'], true) && session()->has('import_lista_id')) {
                $listId = (int) session('import_lista_id');
            }

            $stats = session('import_stats', ['imported' => 0, 'updated' => 0, 'skipped' => 0]);
            $errors = [];

            DB::beginTransaction();

            foreach ($chunkRows as $index => $row) {
                $rowNumber = $start + $index + 2;

                // Process row (same logic as before)
                $result = $this->processRow($row, $rowNumber, $mapping, $existingContacts, $listId);
                
                if ($result['skipped']) {
                    $stats['skipped']++;
                    if ($result['error']) {
                        $errors[] = $result['error'];
                    }
                } elseif ($result['updated']) {
                    $stats['updated']++;
                } else {
                    $stats['imported']++;
                }
            }

            DB::commit();
            session()->put('import_stats', $stats);

            // Update existing contacts index with new contacts
            $existingContactsData = session('import_existing_contacts', []);
            foreach ($existingContacts as $normalizedPhone => $contact) {
                $existingContactsData[$normalizedPhone] = $contact->id;
            }
            session()->put('import_existing_contacts', $existingContactsData);

            $completed = $end >= $totalRows;
            if ($completed) {
                Storage::disk('imports')->delete($path);
                session()->forget([
                    'import_file', 'import_headers', 'import_columns', 'import_stats', 'import_cancelled',
                    'import_lista_option', 'import_lista_id', 'import_new_list_name', 'import_existing_contacts', 'import_mapping',
                ]);
            }

            return response()->json([
                'completed' => $completed,
                'progress' => round(($end / $totalRows) * 100, 2),
                'processed' => $end,
                'total' => $totalRows,
                'stats' => $stats,
                'errors' => array_slice($errors, 0, 10), // Limit errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao processar chunk', ['error' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'Erro ao processar: ' . $e->getMessage(),
                'completed' => true,
            ], 500);
        }
    }

    /**
     * Process a single row.
     *
     * @param  int|null  $listId  If set, attach the contact (created or updated) to this list.
     */
    private function processRow(array $row, int $rowNumber, array $mapping, &$existingContacts, ?int $listId = null): array
    {
        // Check required fields
        foreach ($mapping as $colIndex => $config) {
            if ($config['required']) {
                $value = trim($row[$colIndex] ?? '');
                if (empty($value)) {
                    return ['skipped' => true, 'error' => "Linha {$rowNumber}: Campo obrigatório está vazio."];
                }
            }
        }

        $contactData = [
            'user_id' => auth()->user()->accountId(),
            'name' => null,
            'phone' => null,
            'email' => null,
            'notes' => null,
        ];

        $customFieldValues = [];

        foreach ($mapping as $colIndex => $config) {
            $value = trim($row[$colIndex] ?? '');
            
            switch ($config['type']) {
                case 'name':
                    $contactData['name'] = $value;
                    break;
                case 'phone':
                    $contactData['phone'] = $this->formatPhone($value);
                    break;
                case 'email':
                    $contactData['email'] = $value;
                    break;
                case 'notes':
                    $contactData['notes'] = $value;
                    break;
                case 'field':
                    if (!empty($value)) {
                        $customFieldValues[$config['field_id']] = $value;
                    }
                    break;
            }
        }

        if (empty($contactData['phone']) || $contactData['phone'] === '(00)00000-0000') {
            return ['skipped' => true, 'error' => "Linha {$rowNumber}: Telefone não informado."];
        }

        if ($contactData['phone'] && !$this->isValidPhone($contactData['phone'])) {
            return ['skipped' => true, 'error' => "Linha {$rowNumber}: Telefone inválido."];
        }

        if (empty($contactData['name'])) {
            $contactData['name'] = 'Contato ' . $rowNumber;
        }

        $normalizedPhone = preg_replace('/\D/', '', $contactData['phone']);
        $existingContact = $existingContacts->get($normalizedPhone);

        if ($existingContact) {
            $existingContact->update([
                'name' => $contactData['name'],
                'email' => $contactData['email'] ?? $existingContact->email,
                'notes' => $contactData['notes'] ?? $existingContact->notes,
            ]);
            $contact = $existingContact;
            $updated = true;
        } else {
            // updateOrCreate guards against race conditions and duplicate-phone edge cases.
            $contact = Contact::updateOrCreate(
                ['user_id' => $contactData['user_id'], 'phone' => $contactData['phone']],
                ['name' => $contactData['name'], 'email' => $contactData['email'], 'notes' => $contactData['notes']],
            );
            $existingContacts->put($normalizedPhone, $contact);
            $updated = $contact->wasRecentlyCreated === false;
        }

        foreach ($customFieldValues as $fieldId => $value) {
            $contact->setFieldValue($fieldId, $value);
        }

        if ($listId) {
            $contact->listas()->syncWithoutDetaching([$listId]);
            app(AutomationEventService::class)->contactAddedToList($contact, $listId);
        }

        return ['skipped' => false, 'updated' => $updated];
    }

    /**
     * Get import status.
     */
    public function status()
    {
        $stats = session('import_stats', ['imported' => 0, 'updated' => 0, 'skipped' => 0]);
        $cancelled = session('import_cancelled', false);
        
        return response()->json([
            'stats' => $stats,
            'cancelled' => $cancelled,
        ]);
    }

    /**
     * Cancel import.
     */
    public function cancel()
    {
        session()->put('import_cancelled', true);
        
        return response()->json([
            'success' => true,
            'message' => 'Importação cancelada.',
        ]);
    }
}
