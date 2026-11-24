<?php

namespace App\Services\WhatsApp\Drivers;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;

class FonnteWhatsAppDriver implements WhatsAppGatewayInterface
{
    public function driverName(): string
    {
        return 'fonnte';
    }

    protected function token(): string
    {
        return (string) config('whatsapp.drivers.fonnte.token', '');
    }

    protected function endpoint(): string
    {
        return (string) config('whatsapp.drivers.fonnte.endpoint', 'https://api.fonnte.com/send');
    }

    public function isHealthy(): bool
    {
        return $this->token() !== '';
    }

    public function sendText(string $phoneDigits, string $message): array
    {
        if ($this->token() === '') {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Token WHATSAPP_FONNTE_TOKEN belum dikonfigurasi.',
            ];
        }

        try {
            $resp = Http::withHeaders(['Authorization' => $this->token()])
                ->timeout((int) config('whatsapp.drivers.fonnte.timeout', 25))
                ->asForm()
                ->post($this->endpoint(), [
                    'target' => $phoneDigits,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            if ($resp->successful() && (bool) $resp->json('status', true)) {
                $ids = $resp->json('id');
                $msgId = is_array($ids) ? ($ids[0] ?? null) : (is_string($ids) ? $ids : null);

                return [
                    'success' => true,
                    'external_message_id' => $msgId,
                    'error' => null,
                    'raw' => $resp->json(),
                ];
            }

            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Fonnte HTTP ' . $resp->status() . ': ' . ($resp->json('reason') ?? mb_strimwidth($resp->body(), 0, 200, '...')),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Fonnte Exception: ' . $e->getMessage(),
            ];
        }
    }

    public function sendDocument(string $phoneDigits, string $absoluteFilePath, string $fileName, string $caption = ''): array
    {
        if ($this->token() === '') {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Token WHATSAPP_FONNTE_TOKEN belum dikonfigurasi.',
            ];
        }

        if (!is_file($absoluteFilePath)) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => "File tidak ditemukan: {$absoluteFilePath}",
            ];
        }

        $maxBytes = (int) config('whatsapp.drivers.fonnte.max_direct_bytes', 15728640);
        if (filesize($absoluteFilePath) > $maxBytes) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Ukuran file melebihi batas kirim langsung Fonnte (15 MB).',
            ];
        }

        try {
            $resp = Http::withHeaders(['Authorization' => $this->token()])
                ->timeout((int) config('whatsapp.drivers.fonnte.timeout', 25))
                ->attach('file', fopen($absoluteFilePath, 'rb'), $fileName)
                ->post($this->endpoint(), [
                    'target' => $phoneDigits,
                    'message' => $caption,
                    'filename' => $fileName,
                    'countryCode' => '62',
                ]);

            if ($resp->successful() && (bool) $resp->json('status', true)) {
                $ids = $resp->json('id');
                $msgId = is_array($ids) ? ($ids[0] ?? null) : (is_string($ids) ? $ids : null);

                return [
                    'success' => true,
                    'external_message_id' => $msgId,
                    'error' => null,
                    'raw' => $resp->json(),
                ];
            }

            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Fonnte File HTTP ' . $resp->status() . ': ' . ($resp->json('reason') ?? mb_strimwidth($resp->body(), 0, 200, '...')),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'external_message_id' => null,
                'error' => 'Fonnte File Exception: ' . $e->getMessage(),
            ];
        }
    }
}
