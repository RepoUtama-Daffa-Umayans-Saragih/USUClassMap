<?php

namespace App\Services\WhatsApp\Drivers;

use App\Contracts\WhatsAppGatewayInterface;
use App\Support\WhatsAppPhoneNormalizer;
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

    protected function dashboardUrl(): string
    {
        return $this->baseUrl() . '/dashboard';
    }

    protected function chatId(string $phoneDigits): string
    {
        return preg_replace('/[^\d]/', '', $phoneDigits) . '@c.us';
    }

    /**
     * Query live WAHA session metadata without throwing on connection failure.
     *
     * @return array{reachable: bool, status: string, me: ?string, dashboard_url: string}
     */
    public function sessionInfo(): array
    {
        try {
            $resp = Http::withHeaders($this->headers())
                ->timeout(2)
                ->get($this->baseUrl() . '/api/sessions/' . $this->session());

            if ($resp->successful()) {
                $status = strtoupper((string) ($resp->json('status') ?? 'UNKNOWN'));
                $me = $resp->json('me.id');

                return [
                    'reachable' => true,
                    'status' => $status !== '' ? $status : 'UNKNOWN',
                    'me' => is_string($me) && $me !== '' ? $me : null,
                    'dashboard_url' => $this->dashboardUrl(),
                ];
            }

            if ($resp->status() === 404) {
                return [
                    'reachable' => true,
                    'status' => 'STOPPED',
                    'me' => null,
                    'dashboard_url' => $this->dashboardUrl(),
                ];
            }

            if (in_array($resp->status(), [401, 403], true)) {
                return [
                    'reachable' => true,
                    'status' => 'AUTH_ERROR',
                    'me' => null,
                    'dashboard_url' => $this->dashboardUrl(),
                ];
            }

            return [
                'reachable' => true,
                'status' => 'ERROR_' . $resp->status(),
                'me' => null,
                'dashboard_url' => $this->dashboardUrl(),
            ];
        } catch (\Throwable) {
            return [
                'reachable' => false,
                'status' => 'OFFLINE',
                'me' => null,
                'dashboard_url' => $this->dashboardUrl(),
            ];
        }
    }

    public function isHealthy(): bool
    {
        $info = $this->sessionInfo();

        return $info['reachable']
            && in_array($info['status'], ['WORKING', 'CONNECTED', 'AUTHENTICATED'], true);
    }

    protected function formatWahaError(string $prefix, ?int $statusCode, string $detail): string
    {
        $senderDisplay = WhatsAppPhoneNormalizer::formatDisplay(
            (string) config('whatsapp.sender_phone', '6281283575656')
        );
        $upper = strtoupper($detail);

        if (str_contains($upper, 'SCAN_QR_CODE') || str_contains($upper, 'STOPPED') || str_contains($upper, 'STARTING')) {
            return "Sesi WAHA belum aktif ({$prefix}). Buka {$this->dashboardUrl()} lalu scan QR / pairing dengan WhatsApp HP {$senderDisplay}. Detail: " . mb_strimwidth($detail, 0, 160, '...');
        }

        if (in_array($statusCode, [401, 403], true)) {
            return "WAHA menolak otentikasi API Key (HTTP {$statusCode}). Periksa WHATSAPP_WAHA_API_KEY di .env.";
        }

        if ($statusCode === null) {
            return "Tidak dapat menghubungi server WAHA ({$this->baseUrl()}): " . mb_strimwidth($detail, 0, 180, '...');
        }

        return "{$prefix} HTTP {$statusCode}: " . mb_strimwidth($detail, 0, 200, '...');
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
                'error' => $this->formatWahaError('WAHA sendText', $resp->status(), $resp->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => $this->formatWahaError('WAHA sendText', null, $e->getMessage()),
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
                'error' => $this->formatWahaError('WAHA sendFile', $resp->status(), $resp->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => $this->formatWahaError('WAHA sendFile', null, $e->getMessage()),
            ];
        }
    }
}
