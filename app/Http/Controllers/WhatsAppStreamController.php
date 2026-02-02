<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppStreamController extends Controller
{
    /**
     * Server-Sent Events stream for the WhatsApp Inbox UI.
     *
     * Frontend reconnects automatically; keep this response short-lived (~55s)
     * so PHP workers are not held forever.
     */
    public function stream(Request $request): Response
    {
        $accountId = (int) auth()->user()->accountId();
        $lastId = (int) $request->query('last_id', 0);
        $once = (bool) $request->boolean('once', false);

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        // Debug/testing mode: emit once and finish (no long-lived connection).
        if ($once) {
            $current = max(0, $lastId);
            $body = '';

            $body .= $this->format('wa.ready', [
                'now' => now()->toIso8601String(),
                'last_id' => $current,
            ]);

            try {
                $events = WhatsAppEvent::query()
                    ->where('user_id', $accountId)
                    ->where('id', '>', $current)
                    ->orderBy('id')
                    ->limit(100)
                    ->get(['id', 'type', 'payload', 'created_at']);
            } catch (\Throwable $e) {
                $body .= $this->format('wa.error', [
                    'code' => 'db_unavailable',
                    'message' => 'Banco de dados indisponível no momento.',
                ]);
                return response($body, 503, $headers);
            }

            foreach ($events as $e) {
                $current = (int) $e->id;

                $payload = is_array($e->payload) ? $e->payload : [];
                $payload['_event_id'] = $current;
                $payload['_event_at'] = optional($e->created_at)->toIso8601String();

                $body .= $this->format($e->type, $payload, $current);
            }

            return response($body, 200, $headers);
        }

        return response()->stream(function () use ($accountId, $lastId) {
            // Reduce buffering as much as possible
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            // Prevent PHP max_execution_time from killing SSE (if allowed)
            @set_time_limit(0);
            @ignore_user_abort(true);

            $current = max(0, $lastId);
            $startedAt = microtime(true);

            $this->emit('wa.ready', [
                'now' => now()->toIso8601String(),
                'last_id' => $current,
            ]);

            // Keep stream lifetime below typical 30s limits; browser will reconnect.
            // (We still try set_time_limit(0) above, but do not rely on it.)
            while (!connection_aborted() && (microtime(true) - $startedAt) < 25) {
                try {
                    $events = WhatsAppEvent::query()
                        ->where('user_id', $accountId)
                        ->where('id', '>', $current)
                        ->orderBy('id')
                        ->limit(50)
                        ->get(['id', 'type', 'payload', 'created_at']);
                } catch (\Throwable $e) {
                    // Best-effort: notify client then back off a bit
                    echo $this->format('wa.error', [
                        'code' => 'db_unavailable',
                        'message' => 'Banco de dados indisponível no momento.',
                    ]);
                    @ob_flush();
                    @flush();
                    usleep(1200000); // ~1.2s
                    continue;
                }

                if ($events->isEmpty()) {
                    // Heartbeat to keep proxies/browsers from timing out
                    echo ": keep-alive\n\n";
                    @ob_flush();
                    @flush();
                    usleep(650000); // ~0.65s
                    continue;
                }

                foreach ($events as $e) {
                    $current = (int) $e->id;

                    $payload = is_array($e->payload) ? $e->payload : [];
                    $payload['_event_id'] = $current;
                    $payload['_event_at'] = optional($e->created_at)->toIso8601String();

                    $this->emit($e->type, $payload, $current);
                }

                @ob_flush();
                @flush();
            }
        }, 200, $headers);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function emit(string $event, array $data, ?int $id = null): void
    {
        echo $this->format($event, $data, $id);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function format(string $event, array $data, ?int $id = null): string
    {
        $event = str_replace(["\n", "\r"], '', $event);

        $out = '';
        if ($id !== null) {
            $out .= "id: {$id}\n";
        }
        $out .= "event: {$event}\n";
        $out .= 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        return $out;
    }
}

