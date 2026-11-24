<?php

namespace App\Services\WhatsApp;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderFile;
use App\Support\WhatsAppPhoneNormalizer;

class WhatsAppPayloadFormatter
{
    /**
     * Pesan 1: Ringkasan Order & SPK Teks ke WhatsApp Operator Toko.
     */
    public function formatStoreSummary(FotocopyOrder $order): string
    {
        $order->loadMissing(['store', 'items', 'files']);
        $place = $order->photocopy_place_resolved;
        $storeName = $place?->name ?? $order->store?->name ?? 'Mitra Fotokopi USU';
        $paidAt = ($order->paid_at ?? now())->timezone('Asia/Jakarta')->format('d M Y, H:i');
        $custWa = WhatsAppPhoneNormalizer::formatDisplay($order->customer_whatsapp ?: $order->customer_phone);

        $lines = [
            '*ORDER CETAK BARU — SUDAH LUNAS*',
            "Toko Tujuan : {$storeName}",
            "Kode Order  : #{$order->order_code}",
            'No. Invoice : ' . ($order->invoice_number ?: '-'),
            'Kode Ambil  : *' . ($order->pickup_code ?: $order->order_code) . '*',
            "Waktu Bayar : {$paidAt} WIB",
            "Status Bayar: *LUNAS* ({$order->getPaymentMethodLabel()})",
            '',
            '*Data Pemesan:*',
            "• Nama  : {$order->customer_name}",
            "• No WA : {$custWa}",
            '• Est. Selesai : ' . ($order->pickup_estimate ?: '+/- 20 menit'),
        ];

        if ($order->files->isNotEmpty()) {
            $lines[] = '';
            $lines[] = "*Daftar Dokumen PDF ({$order->files->count()} File):*";
            foreach ($order->files as $idx => $file) {
                $num = $idx + 1;
                $finish = $file->finishing_option ? " + BUNDEL FINISHING: {$file->finishing_option}" : '';
                $lines[] = "{$num}. `{$file->original_name}` ({$file->page_count} hal x {$file->copies}x | {$file->print_mode}{$finish})";
            }
        }

        $fileLinkedItemIds = $order->files
            ->pluck('fotocopy_order_item_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $otherItems = $order->files->isNotEmpty()
            ? $order->items->reject(fn ($item): bool => in_array((int) $item->id, $fileLinkedItemIds, true))
            : $order->items;

        $offlinePhysicalItems = $otherItems
            ->filter(fn ($item): bool => str_contains((string) $item->category, 'Offline Fisik'))
            ->values();

        $atkGoodsItems = $otherItems
            ->reject(fn ($item): bool => str_contains((string) $item->category, 'Offline Fisik'))
            ->values();

        if ($offlinePhysicalItems->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Layanan Berkas Fisik (OFFLINE — Menunggu Pelanggan Bawa Berkas Asli):*';
            foreach ($offlinePhysicalItems as $item) {
                $sub = number_format($item->subtotal ?? ($item->price * $item->quantity), 0, ',', '.');
                $lines[] = "• {$item->service_name} (x{$item->quantity}) — Rp {$sub} [Bawa Fisik ke Toko]";
            }
        }

        if ($atkGoodsItems->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Barang ATK & Perlengkapan Tambahan:*';
            foreach ($atkGoodsItems as $item) {
                $sub = number_format($item->subtotal ?? ($item->price * $item->quantity), 0, ',', '.');
                $lines[] = "• {$item->service_name} (x{$item->quantity}) — Rp {$sub}";
            }
        }

        if (!empty($order->notes)) {
            $lines[] = '';
            $lines[] = '*Catatan Umum Pelanggan:*';
            $lines[] = "\"{$order->notes}\"";
        }

        $lines[] = '';
        $lines[] = "*TOTAL DIBAYAR: {$order->getFormattedTotal()}*";
        if ($order->files->isNotEmpty()) {
            $lines[] = '_Seluruh file PDF beserta catatan khusus per file & invoice resmi dikirim tepat setelah pesan ini._';
        }

        return implode("\n", $lines);
    }

    /**
     * Pesan 2..N: Caption terstruktur per file PDF lengkap dengan CATATAN KHUSUS USER.
     */
    public function formatPdfCaption(FotocopyOrder $order, FotocopyOrderFile $file, int $index, int $total): string
    {
        $totalSheets = max(1, (int) $file->page_count) * max(1, (int) $file->copies);
        $finishing = $file->finishing_option ?: 'Tanpa Jilid / Lepas';
        $noteText = !empty($file->note) ? $file->note : 'Tidak ada catatan khusus (cetak sesuai setting default).';

        $lines = [
            "*[File {$index}/{$total}] {$file->original_name}*",
            "Order : #{$order->order_code} | Ambil: *" . ($order->pickup_code ?: '-') . '*',
            "Pemesan : {$order->customer_name}",
            'Spec Cetak:',
            "• Jumlah Halaman : {$file->page_count} halaman (Total sisi/lembar: {$totalSheets})",
            "• Jumlah Rangkap : {$file->copies} rangkap",
            "• Layanan Cetak  : {$file->print_mode} ({$file->paper_size})",
            "• Finishing      : {$finishing}",
        ];

        if (!empty($file->finishing_option)) {
            $lines[] = "• *BUNDEL FINISHING:* Cetak file PDF ini terlebih dahulu, lalu langsung proses *{$file->finishing_option}*.";
        }

        if ($file->is_encrypted) {
            $lines[] = '• Status PDF     : *TERKUNCI PASSWORD* (cek catatan user atau hubungi mahasiswa)';
        }

        $lines[] = '';
        $lines[] = '*CATATAN KHUSUS FILE INI:*';
        $lines[] = "\"{$noteText}\"";

        return implode("\n", $lines);
    }

    /**
     * Pesan Terakhir Toko: Caption lampiran Bukti Invoice Resmi (LUNAS).
     */
    public function formatInvoiceCaption(FotocopyOrder $order): string
    {
        return implode("\n", [
            '*BUKTI INVOICE RESMI (LUNAS)*',
            'No. Invoice : ' . ($order->invoice_number ?: '-'),
            "Kode Order  : #{$order->order_code}",
            'Kode Ambil  : *' . ($order->pickup_code ?: '-') . '*',
            "Pemesan     : {$order->customer_name}",
            "Total Lunas : {$order->getFormattedTotal()} ({$order->getPaymentMethodLabel()})",
            'Lampiran ini merupakan arsip bukti pembayaran resmi dari USUClassMap.',
        ]);
    }

    /**
     * Salinan Bukti Invoice ke WhatsApp Mahasiswa (saat lunas).
     */
    public function formatCustomerInvoiceMessage(FotocopyOrder $order): string
    {
        $place = $order->photocopy_place_resolved;
        $storeName = $place?->name ?? $order->store?->name ?? 'Mitra Fotokopi USU';
        $storeLoc = $place?->location_name ?? $order->store?->location_name ?? 'Area Kampus USU';

        return implode("\n", [
            "Halo *{$order->customer_name}*, pembayaran Anda telah kami terima (*LUNAS*).",
            "Toko        : {$storeName}",
            "Kode Order  : #{$order->order_code}",
            'No. Invoice : ' . ($order->invoice_number ?: '-'),
            'Kode Ambil  : *' . ($order->pickup_code ?: $order->order_code) . '*',
            'Est. Siap   : ' . ($order->pickup_estimate ?: '+/- 20 menit'),
            "Lokasi Toko : {$storeLoc}",
            '',
            'Bukti Invoice PDF resmi terlampir di pesan ini. Kami akan mengirim notifikasi WA lagi begitu berkas selesai dicetak.',
        ]);
    }

    /**
     * Notifikasi ke WhatsApp Mahasiswa ketika kasir mengubah status menjadi 'Selesai'.
     */
    public function formatCustomerReadyMessage(FotocopyOrder $order): string
    {
        $place = $order->photocopy_place_resolved;
        $storeName = $place?->name ?? $order->store?->name ?? 'Mitra Fotokopi USU';
        $storeLoc = $place?->location_name ?? $order->store?->location_name ?? 'Area Kampus USU';

        return implode("\n", [
            "Halo *{$order->customer_name}*,",
            "Pesanan Anda *#{$order->order_code}* di *{$storeName}* sudah *SELESAI DICETAK & SIAP DIAMBIL*.",
            'Tunjukkan Kode Ambil: *' . ($order->pickup_code ?: $order->order_code) . '* kepada staf kasir.',
            "Lokasi Toko: {$storeLoc}",
            'Terima kasih telah memesan melalui USUClassMap!',
        ]);
    }

    /**
     * Generate fallback manual wa.me URL for cashier/customer recovery.
     */
    public function buildFallbackWaMeUrl(FotocopyOrder $order): string
    {
        $place = $order->photocopy_place_resolved;
        $rawPhone = $order->store?->whatsapp ?: $place?->whatsapp ?: '6285769737410';

        return WhatsAppPhoneNormalizer::toWaMeUrl($rawPhone, $this->formatStoreSummary($order));
    }
}
