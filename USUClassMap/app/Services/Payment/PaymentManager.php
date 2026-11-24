<?php

namespace App\Services\Payment;

use App\Models\FotocopyOrder;
use App\Services\Fotocopy\OrderPaymentFulfillmentService;
use App\Services\Payment\Contracts\PaymentStrategyInterface;
use App\Services\Payment\DTO\PaymentResult;
use App\Services\Payment\Strategies\BankTransferPaymentStrategy;
use App\Services\Payment\Strategies\CashPaymentStrategy;
use App\Services\Payment\Strategies\QrisPaymentStrategy;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * Cache instantiated strategies.
     */
    protected array $strategies = [];

    public function __construct()
    {
        $this->strategies = [
            'cash' => new CashPaymentStrategy(),
            'qris' => new QrisPaymentStrategy(),
            'bank_transfer' => new BankTransferPaymentStrategy(),
        ];
    }

    /**
     * Resolve the corresponding payment strategy by method name.
     */
    public function getStrategy(?string $method): PaymentStrategyInterface
    {
        $normalized = strtolower(trim((string) $method));

        return match ($normalized) {
            'cash', 'tunai', 'cod' => $this->strategies['cash'],
            'qris' => $this->strategies['qris'],
            'bank_transfer', 'bank', 'transfer', 'va' => $this->strategies['bank_transfer'],
            default => $this->strategies['cash'],
        };
    }

    /**
     * Process payment initialization for an order.
     */
    public function process(FotocopyOrder $order, ?string $method, array $options = []): PaymentResult
    {
        $strategy = $this->getStrategy($method);
        return $strategy->process($order, $options);
    }

    /**
     * Simulate success for an order based on its payment method, then fulfill invoice & WhatsApp dispatch.
     */
    public function simulateSuccess(FotocopyOrder $order): bool
    {
        $strategy = $this->getStrategy($order->payment_method);
        $ok = $strategy->simulateSuccess($order);

        if ($ok && $order->fresh()?->isPaid()) {
            app(OrderPaymentFulfillmentService::class)->handlePaidOrder($order->fresh(), true);
        }

        return $ok;
    }

    /**
     * Get list of supported payment methods with metadata for front-end rendering.
     */
    public function getSupportedMethods(): array
    {
        return [
            [
                'id' => 'qris',
                'name' => 'QRIS Real-Time',
                'description' => 'Scan QR Code langsung via GoPay, OVO, Dana, BCA Mobile, Livin, dll.',
                'badge' => 'Otomatis',
                'is_popular' => true,
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Transfer Bank (Virtual Account)',
                'description' => 'Transfer via BCA, Mandiri, BNI, BRI, atau BSI (Kode PoC: 88019920260001).',
                'badge' => 'Virtual Account',
                'is_popular' => false,
            ],
            [
                'id' => 'cash',
                'name' => 'Bayar di Tempat (Tunai)',
                'description' => 'Bayar tunai saat mengambil hasil fotokopi/cetakan langsung di kasir toko.',
                'badge' => 'Kasir Toko',
                'is_popular' => false,
            ],
        ];
    }
}
