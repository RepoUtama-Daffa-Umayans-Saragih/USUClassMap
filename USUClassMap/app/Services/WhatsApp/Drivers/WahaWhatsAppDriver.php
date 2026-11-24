<?php

namespace App\Services\WhatsApp\Drivers;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;

class WahaWhatsAppDriver implements WhatsAppGatewayInterface
{
    public function driverName(): string
    {
        return 'waha';
    }

    protected function headers(): array
    {
        $headers = ['Accept' => 'application/json'];
        $apiKey = (string) config('whatsapp.drivers.waha.api_key', '');
        if ($apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        return $headers;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('whatsapp.drivers.waha.base_url', 'http://localhost:3000'), '/');
    }

    protected function session(): string
    {
        return (string) config('whatsapp.drivers.waha.session', 'default');
    }

    protected function chatId(string $phoneDigits): string
    {
        return preg_replace('/[^\d]/', '', $phoneDigits) . '@c.us';
    }

    public function isHealthy(): bool
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->timeout(2)
                ->get($this->baseUrl() . '/api/sessions/' . $this->session());

            if (!$resp->successful()) {
                return false;
            }

            $status = strtoupper((string) ($resp->json('status') ?? ''));

            return in_array($status, ['WORKING', 'CONNECTED', 'AUTHENTICATED'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function sendText(string $phoneDigits, string $message): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->timeout((int) config('whatsapp.drivers.waha.timeout', 25))
                ->post($this->baseUrl() . '/api/sendText', [
                    'session' => $this->session(),
                    'chatId' => $this->chatId($phoneDigits),
                    'text' => $message,
                ]);

            if ($resp->successful()) {
                return [
                    'success' => true,
                    'external_message_id' => $resp->json('id') ?? $resp->json('key.id') ?? null,
                    'error' => null,
                    'raw' => $resp->json(),
                ];
            }

            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'WAHA HTTP ' . $resp->status() . ': ' . mb_strimwidth($resp->body(), 0, 240, '...'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'WAHA Exception: ' . $e->getMessage(),
            ];
        }
    }

    public function sendDocument(string $phoneDigits, string $absoluteFilePath, string $fileName, string $caption = ''): array
    {
        if (!is_file($absoluteFilePath)) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => "File tidak ditemukan di disk: {$absoluteFilePath}",
            ];
        }

        try {
            $binary = file_get_contents($absoluteFilePath);
            if ($binary === false) {
                return [
                    'success' => false,
                    'external_message_id' => null,
                    'error' => 'Gagal membaca byte dokumen lokal untuk dikirim ke WAHA.',
                ];
            }

            $mime = mime_content_type($absoluteFilePath) ?: 'application/pdf';
            $payload = [
                'session' => $this->session(),
                'chatId' => $this->chatId($phoneDigits),
                'file' => [
                    'mimetype' => $mime,
                    'filename' => $fileName,
                    'data' => base64_encode($binary),
                ],
                'caption' => $caption,
            ];

            $resp = Http::withHeaders($this->headers())
                ->timeout((int) config('whatsapp.drivers.waha.timeout', 25))
                ->post($this->baseUrl() . '/api/sendFile', $payload);

            if ($resp->successful()) {
                return [
                    'success' => true,
                    'external_message_id' => $resp->json('id') ?? $resp->json('key.id') ?? null,
                    'error' => null,
                    'raw' => $resp->json(),
                ];
            }

            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'WAHA sendFile HTTP ' . $resp->status() . ': ' . mb_strimwidth($resp->body(), 0, 240, '...'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'WAHA sendFile Exception: ' . $e->getMessage(),
            ];
        }
    }
}
