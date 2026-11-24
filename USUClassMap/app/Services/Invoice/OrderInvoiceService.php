<?php

namespace App\Services\Invoice;

use App\Models\FotocopyOrder;
use App\Services\Payment\Services\QrCodeSvgGenerator;
use App\Services\Pdf\PdfMetadataInspectorService;
use App\Support\WhatsAppPhoneNormalizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderInvoiceService
{
    /**
     * Generate official invoice PDF on private `local` disk for a photocopy order.
     * Idempotent by default unless `$force = true`.
     */
    public function generateForOrder(FotocopyOrder $order, bool $force = false): string
    {
        $order->loadMissing(['store', 'items', 'files']);

        if (!$force && !empty($order->invoice_pdf_path) && Storage::disk('local')->exists($order->invoice_pdf_path)) {
            return $order->invoice_pdf_path;
        }

        if (empty($order->invoice_number)) {
            $order->invoice_number = $this->generateUniqueInvoiceNumber();
        }

        if (empty($order->pickup_code)) {
            $order->pickup_code = app(PdfMetadataInspectorService::class)->generatePickupCode();
        }

        $place = $order->photocopy_place_resolved;
        $storeName = $place?->name ?? $order->store?->name ?? 'Mitra Fotokopi USU';
        $storeLocation = $place?->location_name ?? $order->store?->location_name ?? 'Area Kampus USU, Medan';
        $storeWhatsapp = WhatsAppPhoneNormalizer::formatDisplay($place?->whatsapp ?: $order->store?->whatsapp ?: '6285769737410');

        $qrPayload = implode('|', [
            'USUCLASSMAP',
            $order->order_code,
            $order->pickup_code,
            $order->invoice_number,
            (string) $order->total_price,
        ]);
        $qrDataUri = QrCodeSvgGenerator::generateDataUri($qrPayload, 110);

        $fileLinkedItemIds = $order->files
            ->pluck('fotocopy_order_item_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $otherItems = $order->files->isNotEmpty()
            ? $order->items->reject(fn ($item): bool => in_array((int) $item->id, $fileLinkedItemIds, true))->values()
            : $order->items;

        $offlinePhysicalItems = $otherItems
            ->filter(fn ($item): bool => str_contains((string) $item->category, 'Offline Fisik'))
            ->values();

        $atkGoodsItems = $otherItems
            ->reject(fn ($item): bool => str_contains((string) $item->category, 'Offline Fisik'))
            ->values();

        $pdf = Pdf::loadView('features.fotocopy.pdf.invoice', [
            'order' => $order,
            'storeName' => $storeName,
            'storeLocation' => $storeLocation,
            'storeWhatsapp' => $storeWhatsapp,
            'files' => $order->files,
            'otherItems' => $otherItems,
            'offlinePhysicalItems' => $offlinePhysicalItems,
            'atkGoodsItems' => $atkGoodsItems,
            'qrDataUri' => $qrDataUri,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        $relativePath = sprintf(
            'invoices/%s/%s/%s.pdf',
            date('Y'),
            date('m'),
            preg_replace('/[^A-Za-z0-9\-]/', '', $order->invoice_number)
        );

        Storage::disk('local')->put($relativePath, $pdf->output());

        $order->invoice_pdf_path = $relativePath;
        $order->save();

        return $relativePath;
    }

    protected function generateUniqueInvoiceNumber(): string
    {
        do {
            $candidate = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        } while (FotocopyOrder::where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
