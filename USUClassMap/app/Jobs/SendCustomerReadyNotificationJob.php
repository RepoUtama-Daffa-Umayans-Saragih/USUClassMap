<?php

namespace App\Jobs;

use App\Models\FotocopyOrder;
use App\Models\WhatsappDispatchLog;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppPayloadFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerReadyNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId)
    {
    }

    public function handle(WhatsAppManager $waManager, WhatsAppPayloadFormatter $formatter): void
    {
        $order = FotocopyOrder::with(['store'])->find($this->orderId);
        if (!$order || $order->order_status !== 'Selesai') {
            return;
        }

        $customerWa = $order->normalized_customer_whatsapp;
        if (!$customerWa) {
            return;
        }

        $alreadyNotified = WhatsappDispatchLog::where('fotocopy_order_id', $order->id)
            ->where('recipient_role', 'customer')
            ->where('message_type', 'status_update')
            ->where('status', 'sent')
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $driver = $waManager->driver();
        $message = $formatter->formatCustomerReadyMessage($order);
        $res = $driver->sendText($customerWa, $message);

        WhatsappDispatchLog::create([
            'fotocopy_order_id' => $order->id,
            'recipient_phone' => $customerWa,
            'recipient_role' => 'customer',
            'message_type' => 'status_update',
            'payload_json' => ['text' => $message],
            'attachment_path' => null,
            'driver' => $driver->driverName(),
            'external_message_id' => $res['external_message_id'] ?? null,
            'status' => ($res['success'] ?? false) ? 'sent' : 'failed',
            'attempts' => max(1, (int) $this->attempts()),
            'error_message' => $res['error'] ?? null,
            'sent_at' => ($res['success'] ?? false) ? now() : null,
        ]);
    }
}
