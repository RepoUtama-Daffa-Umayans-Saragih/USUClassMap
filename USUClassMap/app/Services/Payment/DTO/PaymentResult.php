<?php

namespace App\Services\Payment\DTO;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $paymentMethod,
        public ?string $paymentChannel = null,
        public string $paymentStatus = 'pending',
        public array $payload = [],
        public array $instructions = [],
        public ?string $qrCodeSvg = null,
        public ?string $accountNumber = null,
        public ?string $accountName = null,
        public string $message = 'Instruksi pembayaran berhasil dibuat'
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'payment_method' => $this->paymentMethod,
            'payment_channel' => $this->paymentChannel,
            'payment_status' => $this->paymentStatus,
            'payload' => $this->payload,
            'instructions' => $this->instructions,
            'qr_code_svg' => $this->qrCodeSvg,
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
            'message' => $this->message,
        ];
    }
}
