<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppInstance;
use App\Services\EvolutionApiHttpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppEvolutionController extends Controller
{
    private EvolutionApiHttpClient $client;

    public function __construct(EvolutionApiHttpClient $client)
    {
        $this->client = $client;
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
            WhatsAppInstance::updateOrCreate(
                ['instance_name' => $instanceNameFromApi],
                [
                    'user_id' => auth()->id(),
                    'whatsapp_number' => $whatsappNumber,
                    'instance_name' => $instanceNameFromApi,
                    'instance_token' => $instanceToken,
                    'status' => $data['instance']['status'] ?? 'connecting',
                    'metadata' => [
                        'build' => $build,
                        'evolution' => [
                            'http_status' => $resp['status'],
                            'response' => $data,
                            'response_text' => $resp['text'],
                        ],
                    ],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'build' => $build,
            'http_status' => $resp['status'],
            'instanceName' => $instanceNameFromApi,
            'hash' => $instanceToken,
            'qrcode' => [
                'base64' => $qrcodeBase64, // data:image/png;base64,...
            ],
            'qrText' => $qrcodeBase64 ? null : $qrText, // texto bruto (2@...) para gerar QR via lib, se quiser
            'pairingCode' => $pairingCode,
            'raw' => $data, // útil para debug; remova depois se quiser
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
            'build' => $build,
            'http_status' => $resp['status'],
            'instanceName' => $instance,
            'qrcode' => [
                'base64' => $qrcodeBase64,
            ],
            'qrText' => $qrcodeBase64 ? null : $qrText,
            'pairingCode' => $pairingCode,
            'raw' => $data,
        ]);
    }
}

