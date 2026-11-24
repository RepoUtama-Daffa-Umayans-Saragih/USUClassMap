<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\Drivers\FonnteWhatsAppDriver;
use App\Services\WhatsApp\Drivers\SandboxWhatsAppDriver;
use App\Services\WhatsApp\Drivers\WahaWhatsAppDriver;

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
     * Provide connection health summary for Admin Fotokopi status indicator.
     *
     * @return array{driver: string, healthy: bool, label: string, waha_url: string}
     */
    public function statusSummary(): array
    {
        $driver = $this->driver();
        $healthy = $driver->isHealthy();

        $label = match ($driver->driverName()) {
            'waha' => $healthy ? 'WAHA Docker Terhubung' : 'WAHA Offline (Siap Fallback)',
            'fonnte' => $healthy ? 'Fonnte Cloud API Aktif' : 'Token Fonnte Belum Diisi',
            default => 'Mode Sandbox Simulator (Audit Chat Lokal)',
        };

        return [
            'driver' => $driver->driverName(),
            'healthy' => $healthy,
            'label' => $label,
            'waha_url' => (string) config('whatsapp.drivers.waha.base_url', 'http://localhost:3000'),
        ];
    }
}
