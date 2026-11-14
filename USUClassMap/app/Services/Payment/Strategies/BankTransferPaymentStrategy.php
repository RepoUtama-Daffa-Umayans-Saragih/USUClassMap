<?php

namespace App\Services\Payment\Strategies;

use App\Models\FotocopyOrder;
use App\Services\Payment\Contracts\PaymentStrategyInterface;
use App\Services\Payment\DTO\PaymentResult;

class BankTransferPaymentStrategy implements PaymentStrategyInterface
{
    /**
     * Process Bank Transfer payment initialization with PoC static VA code and dynamic bank channel.
     */
    public function process(FotocopyOrder $order, array $options = []): PaymentResult
    {
        $rawInput = trim((string)($options['payment_channel'] ?? 'BCA'));
        $supportedBanks = config('payment.bank_transfer.supported_banks', []);

        // Case-insensitive lookup for supported banks
        $lookup = [];
        foreach ($supportedBanks as $key => $conf) {
            $lookup[strtoupper($key)] = $key;
        }

        $canonicalBank = $lookup[strtoupper($rawInput)] ?? 'BCA';
        $bankConfig = $supportedBanks[$canonicalBank] ?? [];
        $staticBankCode = config('payment.bank_transfer.static_bank_code', '88019920260001');
        $accountName = config('payment.bank_transfer.account_name', 'USUClassMap Fotokopi');
        $instructions = $bankConfig['instructions'] ?? $this->getInstructions($order);

        $payload = [
            'method' => 'bank_transfer',
            'channel' => $canonicalBank,
            'bank_name' => $bankConfig['name'] ?? $canonicalBank,
            'bank_code' => $bankConfig['code'] ?? '000',
            'account_number' => $staticBankCode,
            'account_name' => $accountName,
            'instructions' => $instructions,
            'amount' => (int) round($order->total_price),
            'order_code' => $order->order_code,
            'created_at' => now()->toIso8601String(),
        ];

        $order->payment_method = 'bank_transfer';
        $order->payment_channel = $canonicalBank;
        $order->payment_status = 'pending';
        $order->payment_payload = $payload;
        $order->save();

        return new PaymentResult(
            success: true,
            paymentMethod: 'bank_transfer',
            paymentChannel: $canonicalBank,
            paymentStatus: 'pending',
            payload: $payload,
            instructions: $instructions,
            accountNumber: $staticBankCode,
            accountName: $accountName,
            message: "Nomor Virtual Account {$canonicalBank} berhasil diterbitkan. Silakan transfer tepat sesuai nominal."
        );
    }

    /**
     * Simulate bank transfer incoming confirmation.
     */
    public function simulateSuccess(FotocopyOrder $order): bool
    {
        $order->payment_status = 'paid';
        $order->paid_at = now();

        if ($order->order_status === 'Menunggu') {
            $order->order_status = 'Diproses';
        }

        $payload = $order->payment_payload ?? [];
        $payload['paid_via'] = 'Bank VA Callback Simulator';
        $payload['simulated_at'] = now()->toIso8601String();
        $payload['bank_reference'] = 'TRX-' . strtoupper($order->payment_channel ?? 'VA') . '-' . time();
        $order->payment_payload = $payload;

        return $order->save();
    }

    /**
     * Get instructions for bank transfer.
     */
    public function getInstructions(FotocopyOrder $order): array
    {
        $channel = strtoupper($order->payment_channel ?? 'BCA');
        $bankConfig = config("payment.bank_transfer.supported_banks.{$channel}");

        if (!empty($bankConfig['instructions'])) {
            return $bankConfig['instructions'];
        }

        return [
            "Buka aplikasi mobile banking atau ATM {$channel}.",
            "Pilih menu Transfer > Virtual Account.",
            "Masukkan nomor Virtual Account: " . config('payment.bank_transfer.static_bank_code', '88019920260001'),
            "Pastikan nama penerima adalah " . config('payment.bank_transfer.account_name', 'USUClassMap Fotokopi'),
            "Periksa total pembayaran Rp " . number_format($order->total_price, 0, ',', '.') . " dan selesaikan transaksi.",
        ];
    }
}
