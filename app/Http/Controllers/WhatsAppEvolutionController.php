<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppEvolutionController extends Controller
{
    private EvolutionApiHttpClient $client;

    public function __construct(EvolutionApiHttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Regra de negócio: por usuário, apenas 1 WhatsApp pode ficar conectado.
     * Ao iniciar uma nova conexão (gerar QR), desconecta automaticamente os demais
     * que estiverem conectados.
     *
     * @return array{disconnected:list<string>, errors:list<array{instance:string,http_status:int,message:string}>}
     */
    private function autoDisconnectOtherConnectedInstances(string $currentInstance): array
    {
        $currentInstance = preg_replace('/\D/', '', (string) $currentInstance);

        $connectedStates = ['open', 'connected', 'online', 'ready'];

        $others = WhatsAppInstance::query()
            ->where('user_id', auth()->user()->accountId())
            ->where('instance_name', '!=', $currentInstance)
            ->whereIn('status', $connectedStates)
            ->get(['id', 'instance_name', 'status', 'metadata']);

        $disconnected = [];
        $errors = [];

        foreach ($others as $wa) {
            $inst = preg_replace('/\D/', '', (string) $wa->instance_name);
            if ($inst === '') {
                continue;
            }

            // Best-effort: tenta logout na Evolution
            $resp = $this->client->delete("/instance/logout/{$inst}");
            $ok = $resp['status'] >= 200 && $resp['status'] < 300;

            $meta = is_array($wa->metadata) ? $wa->metadata : [];
            $meta['auto_disconnect'] = [
                'at' => now()->toIso8601String(),
                'by' => 'single-connected-rule',
                'target_instance' => $currentInstance,
                'http_status' => $resp['status'],
                'response' => $resp['json'],
                'response_text' => $resp['text'],
            ];
            $wa->metadata = $meta;

            if ($ok) {
                $wa->status = 'disconnected';
                $wa->disconnected_at = now();
                $wa->save();
                $disconnected[] = $inst;
            } else {
                // Não força status local se a Evolution não confirmou o logout
                $wa->save();
                $errors[] = [
                    'instance' => $inst,
                    'http_status' => (int) $resp['status'],
                    'message' => 'Falha ao desconectar automaticamente na Evolution.',
                ];
            }
        }

        return [
            'disconnected' => $disconnected,
            'errors' => $errors,
        ];
    }

    /**
     * GET /settings/whatsapp
     * Página de configurações do WhatsApp (UI).
     */
    public function index(): View
    {
        $instances = WhatsAppInstance::query()
            ->where('user_id', auth()->user()->accountId())
            ->orderByDesc('updated_at')
            // Não expor token/metadata no front (nem passar para view)
            ->get(['id', 'instance_name', 'status']);

        return view('settings.whatsapp', [
            'instances' => $instances,
        ]);
    }

    /**
     * GET /settings/whatsapp/api
     * Endpoint simples (JSON) para teste/diagnóstico.
     */
    public function apiIndex(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'WhatsApp Evolution API (novo) está ativo.',
            'endpoints' => [
                'POST /settings/whatsapp/instance' => [
                    'body' => ['whatsapp_number' => '5511999999999'],
                ],
                'GET /settings/whatsapp/connect/{instance}' => [
                    'example' => '/settings/whatsapp/connect/5511999999999',
                ],
                'GET /settings/whatsapp/state/{instance}' => [
                    'example' => '/settings/whatsapp/state/5511999999999',
                ],
                'POST /settings/whatsapp/disconnect/{instance}' => [
                    'example' => '/settings/whatsapp/disconnect/5511999999999',
                ],
                'POST /settings/whatsapp/delete/{instance}' => [
                    'example' => '/settings/whatsapp/delete/5511999999999',
                ],
            ],
        ]);
    }

    /**
     * POST /settings/whatsapp/instance
     * Cria instância na Evolution e salva no banco (whatsapp_instances).
     */
    public function createInstance(Request $request): JsonResponse
    {
        $build = 'wa-api-create-2026-01-28-01';

        if (!$this->client->isConfigured()) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
            ], 400);
        }

        $request->validate([
            'whatsapp_number' => 'required|string',
        ]);

        $whatsappNumber = preg_replace('/\D/', '', (string) $request->input('whatsapp_number'));
        if (strlen($whatsappNumber) < 10) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Número do WhatsApp inválido. Digite com código do país (ex: 5511999999999).',
            ], 422);
        }

        // Regra: ao iniciar uma nova conexão, desconecta qualquer outra instância conectada
        $auto = $this->autoDisconnectOtherConnectedInstances($whatsappNumber);

        $payload = [
            'instanceName' => $whatsappNumber,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ];

        $resp = $this->client->post('/instance/create', $payload);
        $data = is_array($resp['json']) ? $resp['json'] : [];

        $instanceNameFromApi = $data['instance']['instanceName'] ?? $whatsappNumber;
        $instanceToken = $data['hash'] ?? null;

        // Normaliza QR (só imagem data:image...; se vier 2@..., isso é texto)
        $qrcodeBase64 = $data['qrcode']['base64'] ?? null;
        $qrText = $data['qrcode']['code'] ?? null;
        $pairingCode = $data['qrcode']['pairingCode'] ?? null;

        if (is_string($qrcodeBase64) && preg_match('/^\d+@/', $qrcodeBase64)) {
            // veio code no lugar do base64
            $qrText = $qrcodeBase64;
            $qrcodeBase64 = null;
        }
        if (is_string($qrcodeBase64) && !str_starts_with($qrcodeBase64, 'data:image')) {
            // não é imagem
            $qrcodeBase64 = null;
        }

        if (auth()->check()) {
            $accountId = auth()->user()->accountId();

            // Se a instância já existe (inclusive soft-deleted), reaproveita o registro.
            // Isso evita violação de unique (instance_name) ao "recriar" o mesmo número.
            $wa = WhatsAppInstance::withTrashed()
                ->where('instance_name', $instanceNameFromApi)
                ->first();

            // Segurança: impedir "tomar" número de outra conta (unique é global).
            if ($wa && (int) $wa->user_id !== (int) $accountId) {
                return response()->json([
                    'success' => false,
                    'build' => $build,
                    'error' => 'Este número já está vinculado a outra conta.',
                ], 409);
            }

            if (! $wa) {
                $wa = new WhatsAppInstance();
            } elseif ($wa->trashed()) {
                $wa->restore();
            }

            $meta = is_array($wa->metadata) ? $wa->metadata : [];
            $meta['last_create'] = [
                'at' => now()->toIso8601String(),
                'build' => $build,
                'auto_disconnect' => $auto,
                'evolution' => [
                    'http_status' => $resp['status'],
                    'response' => $data,
                    'response_text' => $resp['text'],
                ],
            ];

            $wa->fill([
                'user_id' => $accountId,
                'whatsapp_number' => $whatsappNumber,
                'instance_name' => $instanceNameFromApi,
                'instance_token' => $instanceToken,
                'status' => $data['instance']['status'] ?? 'connecting',
                'metadata' => $meta,
            ]);

            $wa->save();
        }

        return response()->json([
            'success' => true,
            'instanceName' => $instanceNameFromApi,
            'message' => 'Instância criada. Agora conecte via QR Code.',
            'qrcode' => [
                'base64' => $qrcodeBase64, // data:image/png;base64,...
            ],
            'qrText' => $qrcodeBase64 ? null : $qrText,
            'pairingCode' => $pairingCode,
        ]);
    }

    /**
     * GET /settings/whatsapp/connect/{instance}
     * Retorna QRCode atualizado (preferência imagem).
     */
    public function connect(string $instance): JsonResponse
    {
        $build = 'wa-api-connect-2026-01-28-01';

        if (!$this->client->isConfigured()) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
            ], 400);
        }

        $instance = preg_replace('/\D/', '', (string) $instance);
        if ($instance === '') {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instance inválida.',
            ], 422);
        }

        // Regra: ao iniciar uma nova conexão, desconecta qualquer outra instância conectada
        $auto = $this->autoDisconnectOtherConnectedInstances($instance);

        $resp = $this->client->get("/instance/connect/{$instance}", ['qrcode' => true]);
        $data = is_array($resp['json']) ? $resp['json'] : [];

        // Evolution pode devolver base64 direto ou dentro de qrcode.base64
        $qrcodeBase64 = $data['qrcode']['base64'] ?? ($data['base64'] ?? null);
        $qrText = $data['qrcode']['code'] ?? ($data['code'] ?? null);
        $pairingCode = $data['pairingCode'] ?? ($data['qrcode']['pairingCode'] ?? null);

        if (is_string($qrcodeBase64) && preg_match('/^\d+@/', $qrcodeBase64)) {
            $qrText = $qrcodeBase64;
            $qrcodeBase64 = null;
        }
        if (is_string($qrcodeBase64) && str_starts_with($qrcodeBase64, 'data:image')) {
            // ok
        } else {
            $qrcodeBase64 = null;
        }

        return response()->json([
            'success' => true,
            'instanceName' => $instance,
            'message' => 'QR Code atualizado.',
            'qrcode' => [
                'base64' => $qrcodeBase64,
            ],
            'qrText' => $qrcodeBase64 ? null : $qrText,
            'pairingCode' => $pairingCode,
        ]);
    }

    /**
     * GET /settings/whatsapp/state/{instance}
     * Consulta o estado de conexão na Evolution API.
     *
     * Útil para diagnosticar casos em que o QR aparece mas o WhatsApp
     * mostra "não foi possível conectar neste momento".
     */
    public function state(string $instance): JsonResponse
    {
        $build = 'wa-api-state-2026-01-29-01';

        if (!$this->client->isConfigured()) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
            ], 400);
        }

        $instance = preg_replace('/\D/', '', (string) $instance);
        if ($instance === '') {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instance inválida.',
            ], 422);
        }

        $resp = $this->client->get("/instance/connectionState/{$instance}");
        $data = is_array($resp['json']) ? $resp['json'] : [];

        $state = $data['instance']['state'] ?? ($data['state'] ?? ($data['connectionState'] ?? null));

        // Atualiza o registro do usuário (se existir)
        if (auth()->check()) {
            $wa = WhatsAppInstance::query()
                ->where('instance_name', $instance)
                ->where('user_id', auth()->user()->accountId())
                ->first();

            if ($wa) {
                $meta = is_array($wa->metadata) ? $wa->metadata : [];
                $meta['last_state_check'] = [
                    'at' => now()->toIso8601String(),
                    'http_status' => $resp['status'],
                    'response' => $data,
                    'response_text' => $resp['text'],
                ];

                $wa->metadata = $meta;

                if (is_string($state) && $state !== '') {
                    $wa->status = $state;

                    $stateLower = strtolower($state);
                    if (!$wa->connected_at && in_array($stateLower, ['open', 'connected', 'online', 'ready'], true)) {
                        $wa->connected_at = now();
                    }
                    if (!$wa->disconnected_at && in_array($stateLower, ['close', 'closed', 'disconnected', 'offline'], true)) {
                        $wa->disconnected_at = now();
                    }
                }

                $wa->save();
            }
        }

        return response()->json([
            'success' => true,
            'instanceName' => $instance,
            'state' => $state,
        ]);
    }

    /**
     * POST /settings/whatsapp/disconnect/{instance}
     * Faz logout da instância na Evolution (desconectar).
     */
    public function disconnect(string $instance): JsonResponse
    {
        $build = 'wa-api-disconnect-2026-01-29-01';

        if (!$this->client->isConfigured()) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
            ], 400);
        }

        $instance = preg_replace('/\D/', '', (string) $instance);
        if ($instance === '') {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instance inválida.',
            ], 422);
        }

        // Segurança: só permitir operar em instâncias do próprio usuário
        $wa = WhatsAppInstance::query()
            ->where('instance_name', $instance)
            ->where('user_id', auth()->user()->accountId())
            ->first();

        if (!$wa) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instância não encontrada para este usuário.',
            ], 404);
        }

        // Evolution API: DELETE /instance/logout/{instance}
        $resp = $this->client->delete("/instance/logout/{$instance}");
        $data = is_array($resp['json']) ? $resp['json'] : [];

        $wa->status = 'disconnected';
        $wa->disconnected_at = now();

        $meta = is_array($wa->metadata) ? $wa->metadata : [];
        $meta['last_disconnect'] = [
            'at' => now()->toIso8601String(),
            'http_status' => $resp['status'],
            'response' => $data,
            'response_text' => $resp['text'],
        ];
        $wa->metadata = $meta;
        $wa->save();

        return response()->json([
            'success' => true,
            'instanceName' => $instance,
            'status' => $wa->status,
            'message' => 'WhatsApp desconectado com sucesso.',
        ]);
    }

    /**
     * POST /settings/whatsapp/delete/{instance}
     * Remove a instância na Evolution e remove o registro local (soft delete).
     */
    public function delete(string $instance): JsonResponse
    {
        $build = 'wa-api-delete-2026-01-29-01';

        if (!$this->client->isConfigured()) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Evolution API não configurada. Verifique EVOLUTION_API_URL e EVOLUTION_API_KEY no .env',
            ], 400);
        }

        $instance = preg_replace('/\D/', '', (string) $instance);
        if ($instance === '') {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instance inválida.',
            ], 422);
        }

        // Segurança: só permitir operar em instâncias do próprio usuário
        $wa = WhatsAppInstance::query()
            ->where('instance_name', $instance)
            ->where('user_id', auth()->user()->accountId())
            ->first();

        if (!$wa) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'error' => 'Instância não encontrada para este usuário.',
            ], 404);
        }

        // Evolution API: DELETE /instance/delete/{instance}
        $resp = $this->client->delete("/instance/delete/{$instance}");
        $data = is_array($resp['json']) ? $resp['json'] : [];

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            return response()->json([
                'success' => false,
                'build' => $build,
                'http_status' => $resp['status'],
                'error' => 'Falha ao deletar instância na Evolution.',
            ], 502);
        }

        // Marca e remove localmente (soft delete)
        $meta = is_array($wa->metadata) ? $wa->metadata : [];
        $meta['last_delete'] = [
            'at' => now()->toIso8601String(),
            'http_status' => $resp['status'],
            'response' => $data,
            'response_text' => $resp['text'],
        ];
        $wa->metadata = $meta;
        $wa->status = 'deleted';
        $wa->save();
        $wa->delete();

        return response()->json([
            'success' => true,
            'instanceName' => $instance,
            'deleted' => true,
            'message' => 'Instância deletada com sucesso.',
        ]);
    }
}

