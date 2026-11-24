<?php

namespace App\Services\Fotocopy;

use App\Jobs\DispatchOrderToWhatsAppJob;
use App\Models\FotocopyOrder;
use App\Services\Invoice\OrderInvoiceService;

class OrderPaymentFulfillmentService
{
    public function __construct(
        protected OrderInvoiceService $invoiceService
    ) {
    }

    /**
     * Execute post-payment fulfillment strictly ONLY when the order is paid:
     * 1. Generate official invoice PDF (`INV-YYYYMMDD-XXXX.pdf`) on private `local` disk.
     * 2. Dispatch automated WhatsApp burst (summary -> PDFs + notes -> invoice PDF).
     */
    public function handlePaidOrder(FotocopyOrder $order, bool $dispatchWhatsApp = true): void
    {
        if (!$order->isPaid()) {
            return;
        }

        $this->invoiceService->generateForOrder($order);

        if ($dispatchWhatsApp && !$order->isWaDispatched() && class_exists(DispatchOrderToWhatsAppJob::class)) {
            $order->wa_dispatch_status = 'queued';
            $order->save();

            if (config('whatsapp.sync_dispatch', true)) {
                DispatchOrderToWhatsAppJob::dispatchSync($order->id);
            } else {
                DispatchOrderToWhatsAppJob::dispatch($order->id);
            }
        }
    }
}
