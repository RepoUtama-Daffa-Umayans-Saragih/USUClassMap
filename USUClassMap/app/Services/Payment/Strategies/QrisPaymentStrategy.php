<?php

namespace App\Services\Payment\Strategies;

use App\Models\FotocopyOrder;
use App\Services\Payment\Contracts\PaymentStrategyInterface;
use App\Services\Payment\DTO\PaymentResult;
use App\Services\Payment\Services\QrCodeSvgGenerator;
use Illuminate\Support\Str;

class QrisPaymentStrategy implements PaymentStrategyInterface
{
    /**
     * Process QRIS payment initialization with unique dynamic EMVCo payload and SVG QR.
     */
    public function process(FotocopyOrder $order, array $options = []): PaymentResult
    {
        $nonce = strtoupper(Str::random(10));
        $timestamp = now()->timestamp;
        $expiryMinutes = (int) config('payment.qris.expiry_minutes', 15);
        $expiresAt = now()->addMinutes($expiryMinutes);

        $merchantName = config('payment.qris.merchant_name', 'USUClassMap Fotokopi');
        $merchantId = config('payment.qris.merchant_id', 'ID1020260000001');
        $city = config('payment.qris.city', 'Medan');
        $postalCode = config('payment.qris.postal_code', '20155');
        $amount = (int) round($order->total_price);

        // Generate dynamic EMVCo compliant QRIS string
        $qrisPayload = $this->buildEmvcoPayload([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'amount' => $amount,
            'merchant_name' => $merchantName,
            'merchant_id' => $merchantId,
            'city' => $city,
            'postal_code' => $postalCode,
        ]);

        // Generate local vector QR Code SVG
        $qrCodeSvg = QrCodeSvgGenerator::generateSvg($qrisPayload, 280, 4);
        $instructions = $this->getInstructions($order);

        $payload = [
            'method' => 'qris',
            'channel' => 'QRIS',
            'qris_payload' => $qrisPayload,
            'qris_reference' => "QRIS-{$order->id}-" . substr($nonce, 0, 8),
            'qr_code_svg' => $qrCodeSvg,
            'nonce' => $nonce,
            'merchant_name' => $merchantName,
            'amount' => $amount,
            'created_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'instructions' => $instructions,
        ];

        $order->payment_method = 'qris';
        $order->payment_channel = 'QRIS';
        $order->payment_status = 'pending';
        $order->payment_payload = $payload;
        $order->save();

        return new PaymentResult(
            success: true,
            paymentMethod: 'qris',
            paymentChannel: 'QRIS',
            paymentStatus: 'pending',
            payload: $payload,
            instructions: $instructions,
            qrCodeSvg: $qrCodeSvg,
            message: 'QR Code QRIS dinamis berhasil dibuat. Silakan scan sebelum batas waktu habis.'
        );
    }

    /**
     * Simulate QRIS scan & payment success via webhook/callback simulation.
     */
    public function simulateSuccess(FotocopyOrder $order): bool
    {
        $order->payment_status = 'paid';
        $order->paid_at = now();

        if ($order->order_status === 'Menunggu') {
            $order->order_status = 'Diproses';
        }

        $payload = $order->payment_payload ?? [];
        $payload['paid_via'] = 'QRIS Webhook Simulator';
        $payload['simulated_at'] = now()->toIso8601String();
        $payload['settlement_reference'] = 'SETTLE-' . strtoupper(Str::random(10));
        $order->payment_payload = $payload;

        return $order->save();
    }

    /**
     * Get instructions for QRIS payment.
     */
    public function getInstructions(FotocopyOrder $order): array
    {
        return config('payment.qris.instructions', [
            'Buka aplikasi perbankan atau e-wallet pilihan Anda (BCA, Mandiri, GoPay, OVO, ShopeePay, Dana, dll).',
            'Pindai (Scan) gambar QR Code di atas menggunakan menu scan QRIS.',
            'Periksa nama merchant: USUClassMap Fotokopi dan nominal tagihan.',
            'Lanjutkan pembayaran hingga transaksi dinyatakan berhasil di aplikasi Anda.',
        ]);
    }

    /**
     * Build standard EMVCo QRIS compliant payload with dynamic unique transaction data.
     */
    protected function buildEmvcoPayload(array $params): string
    {
        // Tag 00: Payload Format Indicator = '01'
        $raw = $this->formatTag('00', '01');

        // Tag 01: Point of Initiation Method = '12' (Dynamic QR)
        $raw .= $this->formatTag('01', '12');

        // Tag 26: Merchant Account Information (Domestic QRIS)
        $tag26Sub = $this->formatTag('00', 'ID.CO.QRIS.WWW')
                  . $this->formatTag('01', substr($params['merchant_id'], 0, 15))
                  . $this->formatTag('02', 'UME'); // Usaha Mikro
        $raw .= $this->formatTag('26', $tag26Sub);

        // Tag 52: Merchant Category Code = '5999'
        $raw .= $this->formatTag('52', '5999');

        // Tag 53: Transaction Currency = '360' (IDR)
        $raw .= $this->formatTag('53', '360');

        // Tag 54: Transaction Amount
        $amountStr = (string) $params['amount'];
        $raw .= $this->formatTag('54', $amountStr);

        // Tag 58: Country Code = 'ID'
        $raw .= $this->formatTag('58', 'ID');

        // Tag 59: Merchant Name (Cleaned, max 25 chars)
        $cleanMerchant = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $params['merchant_name']), 0, 25);
        $raw .= $this->formatTag('59', $cleanMerchant ?: 'USUClassMap Fotokopi');

        // Tag 60: Merchant City (Cleaned, max 15 chars)
        $cleanCity = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $params['city']), 0, 15);
        $raw .= $this->formatTag('60', $cleanCity ?: 'Medan');

        // Tag 61: Postal Code (max 10 chars)
        $raw .= $this->formatTag('61', substr($params['postal_code'], 0, 10));

        // Tag 62: Additional Data Field Template (Unique Order Ref & Nonce)
        $tag62Sub = $this->formatTag('01', substr($params['order_code'], 0, 20))
                  . $this->formatTag('05', substr("ORD-{$params['order_id']}-{$params['nonce']}", 0, 25))
                  . $this->formatTag('07', 'A01');
        $raw .= $this->formatTag('62', $tag62Sub);

        // Tag 63: CRC16 Checksum
        $rawBeforeCrc = $raw . '6304';
        $checksum = $this->calculateCrc16Ccitt($rawBeforeCrc);

        return $rawBeforeCrc . $checksum;
    }

    /**
     * Format EMVCo TLV (Tag-Length-Value) segment.
     */
    protected function formatTag(string $tag, string $value): string
    {
        $len = strlen($value);
        return sprintf('%s%02d%s', $tag, $len, $value);
    }

    /**
     * Calculate CRC16-CCITT (polynomial 0x1021, initial value 0xFFFF).
     */
    public function calculateCrc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(sprintf('%04X', $crc));
    }
}
