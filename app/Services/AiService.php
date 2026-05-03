<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Gera uma resposta da IA com base no agente e na mensagem do usuário.
     *
     * @param  int         $accountId      ID do usuário dono da conta
     * @param  AiAgent     $agent          Agente com system_prompt e configurações
     * @param  string      $userMessage    Mensagem enviada pelo contato
     * @param  array       $history        Histórico da sessão [{role, content}] — já filtrado por janela temporal
     * @param  string|null $sessionStartAt ISO8601 do início da sessão (injetado no system prompt como marcador)
     * @return string Resposta gerada pela IA
     *
     * @throws \RuntimeException Se a API key não estiver configurada ou a chamada falhar
     */
    public function generateReply(int $accountId, AiAgent $agent, string $userMessage, array $history = [], ?string $sessionStartAt = null): string
    {
        $apiKey = $this->resolveApiKey($accountId);

        $messages = [];

        // System prompt do agente + marcadores de início/fim da sessão
        $systemContent = trim($agent->system_prompt);
        $sessionContext = $this->buildSessionContext($sessionStartAt);
        if ($sessionContext !== '') {
            $systemContent = $systemContent !== ''
                ? $systemContent . "\n\n" . $sessionContext
                : $sessionContext;
        }

        if ($systemContent !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemContent];
        }

        // Histórico filtrado (apenas mensagens da sessão atual)
        foreach ($history as $entry) {
            if (isset($entry['role'], $entry['content']) && $entry['content'] !== '') {
                $messages[] = ['role' => $entry['role'], 'content' => $entry['content']];
            }
        }

        // Mensagem atual do contato
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $model       = $agent->resolvedModel();
        $temperature = $agent->resolvedTemperature();
        $maxTokens   = $agent->resolvedMaxTokens();

        Log::info('[AiService] Chamando OpenAI', [
            'account_id'      => $accountId,
            'agent_id'        => $agent->id,
            'model'           => $model,
            'history_msgs'    => count($history),
            'session_start'   => $sessionStartAt,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(30)
        ->post(self::API_URL, [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ]);

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error('[AiService] Falha na API OpenAI', [
                'account_id' => $accountId,
                'status'     => $response->status(),
                'error'      => $error,
            ]);
            throw new \RuntimeException('Erro na API OpenAI: ' . $error);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('A API OpenAI retornou uma resposta vazia.');
        }

        return trim($content);
    }

    /**
     * Gera o contexto de sessão injetado no system prompt.
     * Informa à IA quando a conversa atual começou e que ela não deve usar contexto anterior.
     */
    private function buildSessionContext(?string $sessionStartAt): string
    {
        if (! $sessionStartAt) {
            return '';
        }

        try {
            $dt = \Carbon\Carbon::parse($sessionStartAt)->setTimezone('America/Sao_Paulo');
            $formatted = $dt->format('d/m/Y \à\s H:i');
        } catch (\Throwable) {
            $formatted = $sessionStartAt;
        }

        return implode("\n", [
            '---',
            '[CONTEXTO DO SISTEMA]',
            "Esta conversa começou em: {$formatted}.",
            'Considere APENAS as mensagens desta sessão como contexto.',
            'Ignore qualquer histórico anterior a este momento.',
            'Quando o fluxo terminar, esta sessão será encerrada.',
            '---',
        ]);
    }

    private function resolveApiKey(int $accountId): string
    {
        $config = AiConfig::where('user_id', $accountId)->first();

        if (! $config || ! $config->openai_api_key) {
            throw new \RuntimeException('Chave da API OpenAI não configurada. Acesse Configurações → Inteligência Artificial.');
        }

        return $config->openai_api_key;
    }
}
