<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGatewayInterface;
use App\Models\FotocopyOrder;
use App\Models\WhatsappDispatchLog;
use App\Services\Invoice\OrderInvoiceService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppPayloadFormatter;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DispatchOrderToWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $orderId,
        public bool $forceResend = false
    ) {
    }

    public function handle(
        WhatsAppManager $waManager,
        WhatsAppPayloadFormatter $formatter,
        OrderInvoiceService $invoiceService
    ): void {
        $order = FotocopyOrder::with(['store', 'items', 'files'])->find($this->orderId);

        // STRICT ANTI-SPAM UNPAID GUARD: pending / unpaid orders MUST NEVER dispatch to WhatsApp
        if (!$order || !$order->isPaid()) {
            return;
        }

        if (empty($order->invoice_pdf_path) || !Storage::disk('local')->exists($order->invoice_pdf_path)) {
            $invoiceService->generateForOrder($order);
            $order->refresh();
            $order->load(['store', 'items', 'files']);
        }

        $place = $order->photocopy_place_resolved;
        $storePhone = WhatsAppPhoneNormalizer::normalize(
            $order->store?->whatsapp ?: $place?->whatsapp ?: '6285769737410'
        ) ?? '6285769737410';

        $driver = $waManager->driver();
        $driverName = $driver->driverName();

        $allSucceeded = true;
        $anySucceeded = false;

        // 1. Pesan 1: Ringkasan Order ke WA Toko
        if ($this->shouldSend($order->id, 'store', 'summary', null)) {
            $summaryText = $formatter->formatStoreSummary($order);
            $res = $driver->sendText($storePhone, $summaryText);
            $this->recordLog($order->id, $storePhone, 'store', 'summary', [
                'text' => $summaryText,
                'fallback_wa_url' => $formatter->buildFallbackWaMeUrl($order),
            ], null, $driverName, $res);

            if ($res['success']) {
                $anySucceeded = true;
            } else {
                $allSucceeded = false;
            }

            if ($driverName !== 'sandbox') {
                usleep(700000);
            }
        }

        // 2. Pesan 2..N: Semua File PDF beserta Spec & Note Khusus Per File
        $files = $order->files->values();
        $totalFiles = $files->count();
        foreach ($files as $idx => $file) {
            if (!$this->shouldSend($order->id, 'store', 'pdf_attachment', $file->stored_path)) {
                continue;
            }

            $caption = $formatter->formatPdfCaption($order, $file, $idx + 1, $totalFiles);
            $absPath = $file->getAbsoluteLocalPath();

            if ($absPath === null) {
                $res = [
                    'success' => false,
                    'external_message_id' => null,
                    'error' => "File PDF '{$file->original_name}' tidak ditemukan di penyimpanan privat.",
                ];
            } else {
                $res = $driver->sendDocument($storePhone, $absPath, $file->original_name, $caption);
            }

            $this->recordLog($order->id, $storePhone, 'store', 'pdf_attachment', [
                'caption' => $caption,
                'file_name' => $file->original_name,
                'spec' => $file->getDisplaySpecSummary(),
                'note' => $file->note,
            ], $file->stored_path, $driverName, $res);

            if ($res['success']) {
                $anySucceeded = true;
            } else {
                $allSucceeded = false;
            }

            if ($driverName !== 'sandbox') {
                usleep(700000);
            }
        }

        // 3. Pesan Terakhir ke Toko: Lampiran Bukti Invoice PDF Resmi
        if ($this->shouldSend($order->id, 'store', 'invoice', $order->invoice_pdf_path)) {
            $invoiceCaption = $formatter->formatInvoiceCaption($order);
            $invoiceFileName = ($order->invoice_number ?: $order->order_code) . '.pdf';
            $invoiceAbsPath = (!empty($order->invoice_pdf_path) && Storage::disk('local')->exists($order->invoice_pdf_path))
                ? Storage::disk('local')->path($order->invoice_pdf_path)
                : null;

            $res = $invoiceAbsPath
                ? $driver->sendDocument($storePhone, $invoiceAbsPath, $invoiceFileName, $invoiceCaption)
                : ['success' => false, 'external_message_id' => null, 'error' => 'File Invoice PDF belum tersedia.'];

            $this->recordLog($order->id, $storePhone, 'store', 'invoice', [
                'caption' => $invoiceCaption,
                'file_name' => $invoiceFileName,
            ], $order->invoice_pdf_path, $driverName, $res);

            if ($res['success']) {
                $anySucceeded = true;
            } else {
                $allSucceeded = false;
            }
        }

        // 4. Salinan Bukti Invoice ke WA Mahasiswa (jika terisi valid)
        $customerWa = $order->normalized_customer_whatsapp;
        if ($customerWa && $this->shouldSend($order->id, 'customer', 'invoice', $order->invoice_pdf_path)) {
            $customerMsg = $formatter->formatCustomerInvoiceMessage($order);
            $invoiceFileName = ($order->invoice_number ?: $order->order_code) . '.pdf';
            $invoiceAbsPath = (!empty($order->invoice_pdf_path) && Storage::disk('local')->exists($order->invoice_pdf_path))
                ? Storage::disk('local')->path($order->invoice_pdf_path)
                : null;

            $res = $invoiceAbsPath
                ? $driver->sendDocument($customerWa, $invoiceAbsPath, $invoiceFileName, $customerMsg)
                : $driver->sendText($customerWa, $customerMsg);

            $this->recordLog($order->id, $customerWa, 'customer', 'invoice', [
                'caption' => $customerMsg,
                'file_name' => $invoiceFileName,
            ], $order->invoice_pdf_path, $driverName, $res);
        }

        // Update status agregat pengiriman order
        if ($driverName === 'sandbox') {
            $order->wa_dispatch_status = 'sandbox';
            $order->wa_dispatched_at = now();
        } elseif ($allSucceeded) {
            $order->wa_dispatch_status = 'sent';
            $order->wa_dispatched_at = now();
        } elseif ($anySucceeded) {
            $order->wa_dispatch_status = 'partial';
            $order->wa_dispatched_at = now();
        } else {
            $order->wa_dispatch_status = 'failed';
        }
        $order->save();

        // Retry only when running inside an async queue worker and live driver failed
        if (!$allSucceeded && $driverName !== 'sandbox' && $this->job !== null && $this->attempts() < $this->tries) {
            throw new RuntimeException("Sebagian atau seluruh pesan WA order #{$order->order_code} gagal dikirim; dijadwalkan retry.");
        }
    }

    protected function shouldSend(int $orderId, string $role, string $type, ?string $attachmentPath): bool
    {
        if ($this->forceResend) {
            return true;
        }

        return !WhatsappDispatchLog::where('fotocopy_order_id', $orderId)
            ->where('recipient_role', $role)
            ->where('message_type', $type)
            ->when($attachmentPath !== null, fn ($q) => $q->where('attachment_path', $attachmentPath))
            ->where('status', 'sent')
            ->exists();
    }

    protected function recordLog(
        int $orderId,
        string $phone,
        string $role,
        string $type,
        array $payload,
        ?string $attachmentPath,
        string $driverName,
        array $result
    ): void {
        WhatsappDispatchLog::create([
            'fotocopy_order_id' => $orderId,
            'recipient_phone' => $phone,
            'recipient_role' => $role,
            'message_type' => $type,
            'payload_json' => $payload,
            'attachment_path' => $attachmentPath,
            'driver' => $driverName,
            'external_message_id' => $result['external_message_id'] ?? null,
            'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
            'attempts' => max(1, (int) $this->attempts()),
            'error_message' => $result['error'] ?? null,
            'sent_at' => ($result['success'] ?? false) ? now() : null,
        ]);
    }
}
