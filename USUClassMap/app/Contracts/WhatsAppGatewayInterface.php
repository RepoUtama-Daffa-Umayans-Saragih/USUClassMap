<?php

namespace App\Contracts;

interface WhatsAppGatewayInterface
{
    /**
     * Send plain or markdown-formatted WhatsApp text message to normalized digits ("62857...").
     *
     * @return array{success: bool, external_message_id: ?string, error: ?string, simulated?: bool, raw?: mixed}
     */
    public function sendText(string $phoneDigits, string $message): array;

    /**
     * Send document/PDF attachment with optional caption to normalized digits ("62857...").
     *
     * @return array{success: bool, external_message_id: ?string, error: ?string, simulated?: bool, raw?: mixed}
     */
    public function sendDocument(string $phoneDigits, string $absoluteFilePath, string $fileName, string $caption = ''): array;

    /**
     * Canonical driver identifier ("waha", "fonnte", "sandbox").
     */
    public function driverName(): string;

    /**
     * Quick connection/session health check.
     */
    public function isHealthy(): bool;
}
