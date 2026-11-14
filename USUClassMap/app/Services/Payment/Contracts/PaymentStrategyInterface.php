<?php

namespace App\Services\Payment\Contracts;

use App\Models\FotocopyOrder;
use App\Services\Payment\DTO\PaymentResult;

interface PaymentStrategyInterface
{
    /**
     * Process order payment initialization, compute payload, and persist to order.
     */
    public function process(FotocopyOrder $order, array $options = []): PaymentResult;

    /**
     * Simulate successful payment transition (for PoC & demo testing).
     */
    public function simulateSuccess(FotocopyOrder $order): bool;

    /**
     * Get user-facing human-readable step-by-step payment instructions.
     */
    public function getInstructions(FotocopyOrder $order): array;
}
