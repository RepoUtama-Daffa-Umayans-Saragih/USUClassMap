<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\Drivers\FonnteWhatsAppDriver;
use App\Services\WhatsApp\Drivers\SandboxWhatsAppDriver;
use App\Services\WhatsApp\Drivers\WahaWhatsAppDriver;
use App\Support\WhatsAppPhoneNormalizer;

class WhatsAppManager
{
    protected array $drivers = [];

    public function __construct()
    {
        $this->drivers = [
            'waha' => new WahaWhatsAppDriver(),
            'fonnte' => new FonnteWhatsAppDriver(),
            'sandbox' => new SandboxWhatsAppDriver(),
        ];
    }

    public function driver(?string $name = null): WhatsAppGatewayInterface
    {
        $key = strtolower(trim((string) ($name ?: config('whatsapp.default', 'sandbox'))));

        return $this->drivers[$key] ?? $this->drivers['sandbox'];
    }

    /**
     * Provide connection health summary for Admin Fotokopi status indicator & payment view.
     *
     * @return array{
     *     driver: string,
     *     healthy: bool,
     *     label: string,
     *     waha_url: string,
     *     dashboard_url: string,
     *     session: string,
     *     session_status: string,
     *     connected_phone: ?string,
     *     sender_phone: string,
     *     sender_phone_display: string
     * }
     */
    public function statusSummary(): array
    {
        $driver = $this->driver();
        $driverName = $driver->driverName();
        $wahaUrl = rtrim((string) config('whatsapp.drivers.waha.base_url', 'http://localhost:3000'), '/');
        $dashboardUrl = $wahaUrl . '/dashboard';
        $session = (string) config('whatsapp.drivers.waha.session', 'default');

        $senderPhone = WhatsAppPhoneNormalizer::normalize(
            (string) config('whatsapp.sender_phone', '6281283575656')
        ) ?? '6281283575656';
        $senderPhoneDisplay = WhatsAppPhoneNormalizer::formatDisplay($senderPhone);

        $sessionStatus = 'SANDBOX';
        $connectedPhone = null;

        /** @var WahaWhatsAppDriver $wahaDriver */
        $wahaDriver = $this->drivers['waha'];
        $shouldProbeWaha = $driverName === 'waha' || !app()->environment('testing');

        if ($shouldProbeWaha) {
            $info = $wahaDriver->sessionInfo();
            $sessionStatus = $info['status'];
            if (!empty($info['me'])) {
                $rawMe = explode('@', $info['me'])[0] ?? $info['me'];
                $formattedMe = WhatsAppPhoneNormalizer::formatDisplay($rawMe);
                $connectedPhone = $formattedMe !== '-' ? $formattedMe : $rawMe;
            }
        }

        if ($driverName === 'waha') {
            $healthy = in_array($sessionStatus, ['WORKING', 'CONNECTED', 'AUTHENTICATED'], true);
            $label = match ($sessionStatus) {
                'WORKING', 'CONNECTED', 'AUTHENTICATED' => 'WAHA Docker Terhubung (Aktif)',
                'SCAN_QR_CODE' => 'WAHA Aktif — Menunggu Scan QR / Pairing HP Pengirim',
                'STARTING' => 'WAHA Sedang Memulai Sesi...',
                'STOPPED' => 'WAHA Aktif — Sesi Belum Dijalankan',
                'AUTH_ERROR' => 'WAHA API Key Tidak Cocok',
                default => 'WAHA Offline (Siap Fallback)',
            };
        } elseif ($driverName === 'fonnte') {
            $healthy = $driver->isHealthy();
            $label = $healthy ? 'Fonnte Cloud API Aktif' : 'Token Fonnte Belum Diisi';
            $sessionStatus = $healthy ? 'WORKING' : 'TOKEN_MISSING';
        } else {
            $healthy = $driver->isHealthy();
            $label = 'Mode Sandbox Simulator (Audit Chat Lokal)';
        }

        return [
            'driver' => $driverName,
            'healthy' => $healthy,
            'label' => $label,
            'waha_url' => $wahaUrl,
            'dashboard_url' => $dashboardUrl,
            'session' => $session,
            'session_status' => $sessionStatus,
            'connected_phone' => $connectedPhone,
            'sender_phone' => $senderPhone,
            'sender_phone_display' => $senderPhoneDisplay,
        ];
    }
}
