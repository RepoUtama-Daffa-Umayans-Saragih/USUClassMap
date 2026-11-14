<?php

namespace App\Services\Payment\Strategies;

use App\Models\FotocopyOrder;
use App\Services\Payment\Contracts\PaymentStrategyInterface;
use App\Services\Payment\DTO\PaymentResult;

class CashPaymentStrategy implements PaymentStrategyInterface
{
    /**
     * Process cash payment initialization at cashier.
     */
    public function process(FotocopyOrder $order, array $options = []): PaymentResult
    {
        $instructions = $this->getInstructions($order);

        $payload = [
            'method' => 'cash',
            'channel' => 'CASHIER',
            'store_id' => $order->photocopy_place_id,
            'store_name' => $order->photocopyPlace?->name ?? 'Kasir Fotokopi USU',
            'store_address' => $order->photocopyPlace?->address ?? 'Sekitar Kampus USU',
            'order_code' => $order->order_code,
            'total_amount' => (int) $order->total_price,
            'instructions' => $instructions,
            'note' => 'Pembayaran dilakukan secara tunai langsung di kasir toko saat pengambilan pesanan.',
        ];

        $order->payment_method = 'cash';
        $order->payment_channel = 'CASHIER';
        $order->payment_status = 'pending';
        $order->payment_payload = $payload;
        $order->save();

        return new PaymentResult(
            success: true,
            paymentMethod: 'cash',
            paymentChannel: 'CASHIER',
            paymentStatus: 'pending',
            payload: $payload,
            instructions: $instructions,
            message: 'Instruksi pembayaran tunai di kasir berhasil dibuat.'
        );
    }

    /**
     * Simulate cashier receiving cash payment.
     */
    public function simulateSuccess(FotocopyOrder $order): bool
    {
        $order->payment_status = 'paid';
        $order->paid_at = now();

        if ($order->order_status === 'Menunggu') {
            $order->order_status = 'Diproses';
        }

        $payload = $order->payment_payload ?? [];
        $payload['simulated_at'] = now()->toIso8601String();
        $payload['simulated_by'] = 'Cashier Simulation';
        $order->payment_payload = $payload;

        return $order->save();
    }

    /**
     * Get instructions for cash payment.
     */
    public function getInstructions(FotocopyOrder $order): array
    {
        $storeName = $order->photocopyPlace?->name ?? 'Toko Fotokopi';
        return config('payment.cash.instructions', [
            "Datangi langsung kasir {$storeName} sesuai lokasi tertera.",
            "Tunjukkan Kode Referensi Pesanan (#{$order->order_code}) kepada petugas kasir.",
            "Lakukan pembayaran tunai pas sebesar Rp " . number_format($order->total_price, 0, ',', '.') . " saat pengambilan dokumen.",
        ]);
    }
}
