<?php

namespace App\Services\Pdf;

use App\Support\CatalogBehaviorClassifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PdfMetadataInspectorService
{
    public const MAX_FILE_BYTES = 26214400; // 25 MB per file
    public const MAX_TOTAL_BYTES = 83886080; // 80 MB total per checkout
    public const MAX_FILES_PER_ORDER = 10;

    /**
     * Check whether the uploaded file begins with "%PDF-" magic bytes.
     */
    public function hasValidPdfMagicBytes(UploadedFile $file): bool
    {
        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            return false;
        }

        $handle = @fopen($realPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    /**
     * Validate an uploaded public checkout file as genuine PDF and inspect metadata server-side.
     *
     * @return array{page_count: int, is_encrypted: bool, file_size: int, mime_type: string, original_name: string}
     */
    public function inspectPdf(UploadedFile $file, ?int $clientPageCount = null, bool $clientEncrypted = false): array
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Upload file tidak lengkap atau mengalami gangguan jaringan.');
        }

        $originalName = $file->getClientOriginalName() ?: 'dokumen.pdf';
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new InvalidArgumentException("File '{$originalName}' harus berformat .pdf.");
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new InvalidArgumentException("File '{$originalName}' berukuran 0 byte.");
        }
        if ($size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException("File '{$originalName}' melebihi batas maksimal 25 MB.");
        }

        if (!$this->hasValidPdfMagicBytes($file)) {
            throw new InvalidArgumentException("File '{$originalName}' ditolak: header PDF asli (%PDF-) tidak ditemukan.");
        }

        $content = @file_get_contents($file->getRealPath());
        $isEncrypted = $clientEncrypted || ($content !== false && $this->detectEncryptedPdf($content));

        $serverPages = $content !== false ? $this->estimatePdfPageCountFromContent($content) : null;
        if ($serverPages !== null && $serverPages > 0 && !$isEncrypted) {
            $pageCount = $serverPages;
        } else {
            $pageCount = max(1, min(5000, (int) ($clientPageCount ?? 1)));
        }

        return [
            'page_count' => $pageCount,
            'is_encrypted' => $isEncrypted,
            'file_size' => $size,
            'mime_type' => 'application/pdf',
            'original_name' => $originalName,
        ];
    }

    /**
     * Detect /Encrypt marker inside raw PDF content.
     */
    public function detectEncryptedPdf(string $rawContent): bool
    {
        return preg_match('/\/Encrypt\b/', $rawContent) === 1;
    }

    /**
     * Estimate page count by inspecting /Type /Page leaves and page tree /Count values.
     */
    public function estimatePdfPageCountFromContent(string $rawContent): ?int
    {
        $directCount = 0;
        if (preg_match_all('/\/Type\s*\/Page(?![a-zA-Z0-9_])/', $rawContent, $matches)) {
            $directCount = count($matches[0]);
        }

        if ($directCount > 0) {
            return $directCount;
        }

        if (preg_match_all('/\/Count\s+(\d+)/', $rawContent, $countMatches) && !empty($countMatches[1])) {
            $maxCount = max(array_map('intval', $countMatches[1]));
            if ($maxCount > 0 && $maxCount <= 10000) {
                return $maxCount;
            }
        }

        return null;
    }

    /**
     * Store uploaded document onto private local disk with sanitized filename.
     */
    public function storePrivateFile(
        UploadedFile $file,
        string $orderCode,
        int $sortOrder = 1,
        ?string $forcedExtension = null
    ): string {
        $originalName = $file->getClientOriginalName() ?: 'dokumen.pdf';
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($baseName) ?: 'dokumen';
        $ext = strtolower($forcedExtension ?: ($file->getClientOriginalExtension() ?: 'pdf'));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'pdf';

        $safeOrderCode = preg_replace('/[^A-Za-z0-9\-]/', '', $orderCode) ?: 'ORD';
        $dir = sprintf('fotocopy_orders/%s/%s/%s', date('Y'), date('m'), $safeOrderCode);
        $fileName = sprintf('%02d-%s-%s.%s', max(1, $sortOrder), substr($slug, 0, 55), Str::lower(Str::random(4)), $ext);

        $storedPath = $file->storeAs($dir, $fileName, 'local');
        if ($storedPath === false) {
            throw new InvalidArgumentException("Gagal menyimpan file '{$originalName}' ke penyimpanan privat.");
        }

        return $storedPath;
    }

    /**
     * Authoritative per-file breakdown calculation (separating print charges and bundled finishing charges).
     *
     * @return array{print_qty: int, print_unit_price: int, print_subtotal: int, finishing_qty: int, finishing_unit_price: int, finishing_subtotal: int, total_subtotal: int}
     */
    public function calculatePdfBreakdown(
        int $pageCount,
        int $copies,
        int $printUnitPrice,
        int $finishingPrice = 0,
        ?string $finishingUnit = null,
        ?string $finishingName = null
    ): array {
        $pages = max(1, $pageCount);
        $numCopies = max(1, $copies);
        $unit = max(0, $printUnitPrice);
        $finishUnit = max(0, $finishingPrice);

        $printQty = $pages * $numCopies;
        $printSubtotal = $printQty * $unit;

        $hasFinishing = $finishUnit > 0 || !empty($finishingName);
        if ($hasFinishing) {
            $finishingQty = CatalogBehaviorClassifier::isPerSheetFinishing($finishingUnit, $finishingName)
                ? ($pages * $numCopies)
                : $numCopies;
        } else {
            $finishingQty = 0;
        }
        $finishingSubtotal = $finishingQty * $finishUnit;

        return [
            'print_qty' => $printQty,
            'print_unit_price' => $unit,
            'print_subtotal' => $printSubtotal,
            'finishing_qty' => $finishingQty,
            'finishing_unit_price' => $finishUnit,
            'finishing_subtotal' => $finishingSubtotal,
            'total_subtotal' => $printSubtotal + $finishingSubtotal,
        ];
    }

    /**
     * Authoritative per-file subtotal calculation.
     */
    public function calculatePdfSubtotal(int $pageCount, int $copies, int $printUnitPrice, int $finishingPrice = 0): int
    {
        return $this->calculatePdfBreakdown($pageCount, $copies, $printUnitPrice, $finishingPrice)['total_subtotal'];
    }

    /**
     * Generate concise pickup verification code (e.g. "AMB-48KP").
     */
    public function generatePickupCode(): string
    {
        return 'AMB-' . random_int(10, 99) . strtoupper(Str::random(2));
    }

    /**
     * Calculate human-readable pickup estimate based on total print volume and queue depth.
     */
    public function estimatePickupTime(int $totalPagesToPrint, int $activeQueueCount = 0): string
    {
        $minutes = 10 + (int) ceil(max(1, $totalPagesToPrint) / 25) * 5 + (max(0, $activeQueueCount) * 5);
        $minutes = min(240, max(15, $minutes));
        $readyAt = now('Asia/Jakarta')->addMinutes($minutes)->format('H:i');

        return "+/- {$minutes} menit (siap ~{$readyAt} WIB)";
    }

    /**
     * Format byte count to KB or MB string.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
