<?php

namespace App\View\Components;

use App\Models\WhatsAppInstance;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $accountId = auth()->check() ? auth()->user()->accountId() : null;

        $connectedStates = ['open', 'connected', 'online', 'ready'];

        $latestInstance = null;
        if (is_int($accountId)) {
            $latestInstance = WhatsAppInstance::query()
                ->where('user_id', $accountId)
                ->orderByDesc('updated_at')
                ->first(['id', 'instance_name', 'status', 'updated_at']);
        }

        $status = $latestInstance?->status;
        $statusLower = is_string($status) ? strtolower($status) : '';

        $hasWhatsappInstance = (bool) $latestInstance;
        $whatsappConnected = $hasWhatsappInstance && in_array($statusLower, $connectedStates, true);

        return view('layouts.app', [
            'hasWhatsappInstance' => $hasWhatsappInstance,
            'whatsappConnected' => $whatsappConnected,
            'whatsappConnectionStatus' => $status,
        ]);
    }
}
