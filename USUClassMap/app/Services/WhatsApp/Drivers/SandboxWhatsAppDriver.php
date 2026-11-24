<?php

namespace App\Services\WhatsApp\Drivers;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Str;

class SandboxWhatsAppDriver implements WhatsAppGatewayInterface
{
    public function driverName(): string
    {
        return 'sandbox';
    }

    public function isHealthy(): bool
    {
        return true;
    }

    public function sendText(string $phoneDigits, string $message): array
    {
        return [
            'success' => true,
            'external_message_id' => 'SANDBOX-' . strtoupper(Str::random(10)),
            'error' => null,
            'simulated' => true,
        ];
    }

    public function sendDocument(string $phoneDigits, string $absoluteFilePath, string $fileName, string $caption = ''): array
    {
        return [
            'success' => true,
            'external_message_id' => 'SANDBOX-DOC-' . strtoupper(Str::random(10)),
            'error' => null,
            'simulated' => true,
            'file' => $fileName,
        ];
    }
}
