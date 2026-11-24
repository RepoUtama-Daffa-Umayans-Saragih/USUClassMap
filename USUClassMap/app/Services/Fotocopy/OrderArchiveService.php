<?php

namespace App\Services\Fotocopy;

use App\Models\FotocopyOrder;
use App\Support\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class OrderArchiveService
{
    /**
     * Build a temporary ZIP archive containing all PDF documents, Invoice PDF (if paid),
     * and structured CATATAN_CETAK.txt for an order.
     */
    public function buildOrderZip(FotocopyOrder $order): string
    {
        $order->loadMissing(['items', 'files', 'store']);

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive tidak aktif di server.');
        }

        $tempDir = storage_path('app/private/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'order-' . $order->order_code . '-' . Str::lower(Str::random(6)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip ZIP dokumen.');
        }

        $txtLines = [
            '=== SURAT PERINTAH KERJA (SPK) CETAK - USUSTARTERMAP ===',
            "Kode Order    : #{$order->order_code}",
            'No. Invoice   : ' . ($order->invoice_number ?: '-'),
            'Kode Ambil    : ' . ($order->pickup_code ?: '-'),
            "Nama Pemesan  : {$order->customer_name}",
            'No. WA / HP   : ' . WhatsAppPhoneNormalizer::formatDisplay($order->customer_whatsapp ?: $order->customer_phone),
            "Status Bayar  : {$order->payment_status} ({$order->getPaymentMethodLabel()})",
            "Total Tagihan : {$order->getFormattedTotal()}",
            'Catatan Umum  : ' . ($order->notes ?: '-'),
            '',
            '--- DAFTAR FILE DOKUMEN ---',
        ];

        $attachedCount = 0;
        if ($order->files->isNotEmpty()) {
            foreach ($order->files as $idx => $file) {
                $num = $idx + 1;
                $txtLines[] = sprintf(
                    '%d. %s | %d hal x %d rangkap | %s (%s)%s',
                    $num,
                    $file->original_name,
                    $file->page_count,
                    $file->copies,
                    $file->print_mode,
                    $file->paper_size,
                    $file->finishing_option ? " + {$file->finishing_option}" : ''
                );
                $txtLines[] = '   CATATAN KHUSUS: ' . ($file->note ?: '-');

                $disk = $file->storage_disk ?: 'local';
                if (!empty($file->stored_path) && Storage::disk($disk)->exists($file->stored_path)) {
                    $safeEntryName = sprintf('%02d_%s', $num, basename($file->original_name));
                    $zip->addFromString($safeEntryName, (string) Storage::disk($disk)->get($file->stored_path));
                    $attachedCount++;
                }
            }
        } elseif ($order->file_path) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($order->file_path)) {
                    $zip->addFromString('01_' . ($order->file_name ?: 'dokumen.pdf'), (string) Storage::disk($disk)->get($order->file_path));
                    $attachedCount++;
                    break;
                }
            }
        }

        if (!empty($order->invoice_pdf_path) && Storage::disk('local')->exists($order->invoice_pdf_path)) {
            $zip->addFromString(
                ($order->invoice_number ?: 'INVOICE') . '.pdf',
                (string) Storage::disk('local')->get($order->invoice_pdf_path)
            );
        }

        $txtLines[] = '';
        $txtLines[] = '--- RINCIAN ITEM ORDER ---';
        foreach ($order->items as $it) {
            $txtLines[] = "- {$it->service_name} (x{$it->quantity}) = Rp " . number_format($it->subtotal ?? ($it->price * $it->quantity), 0, ',', '.');
        }

        $zip->addFromString('CATATAN_CETAK.txt', implode(PHP_EOL, $txtLines) . PHP_EOL);
        $zip->close();

        if ($attachedCount === 0 && empty($order->invoice_pdf_path)) {
            @unlink($zipPath);
            throw new RuntimeException('Belum ada file PDF fisik atau Invoice yang tersedia untuk diunduh.');
        }

        return $zipPath;
    }
}
