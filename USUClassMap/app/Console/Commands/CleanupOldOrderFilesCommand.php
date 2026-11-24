<?php

namespace App\Console\Commands;

use App\Models\FotocopyOrderFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldOrderFilesCommand extends Command
{
    protected $signature = 'fotocopy:cleanup-files
                            {--days= : Jumlah hari retensi file setelah pesanan Selesai/Dibatalkan (default dari config)}
                            {--dry-run : Tampilkan daftar file yang memenuhi syarat tanpa menghapus}';

    protected $description = 'Bersihkan file PDF fisik pesanan lama dari disk privat sesuai kebijakan retensi (tanpa menghapus riwayat transaksi/invoice).';

    public function handle(): int
    {
        $retentionDays = (int) ($this->option('days') ?: config('whatsapp.retention_days', 14));
        $retentionDays = max(1, $retentionDays);
        $dryRun = (bool) $this->option('dry-run');

        $completedCutoff = now()->subDays($retentionDays);
        $abandonedPendingCutoff = now()->subHours(48);

        $candidates = FotocopyOrderFile::with('order')
            ->whereNull('deleted_at')
            ->whereHas('order', function ($q) use ($completedCutoff, $abandonedPendingCutoff): void {
                $q->where(function ($sub) use ($completedCutoff): void {
                    $sub->whereIn('order_status', ['Selesai', 'Dibatalkan'])
                        ->where('updated_at', '<=', $completedCutoff);
                })->orWhere(function ($sub) use ($abandonedPendingCutoff): void {
                    $sub->where('payment_status', 'pending')
                        ->where('created_at', '<=', $abandonedPendingCutoff);
                });
            })
            ->get();

        $cleaned = 0;
        foreach ($candidates as $file) {
            if ($dryRun) {
                $this->line("[DRY-RUN] Akan dibersihkan: #{$file->id} {$file->original_name} ({$file->stored_path})");
                $cleaned++;
                continue;
            }

            $disk = $file->storage_disk ?: 'local';
            if (!empty($file->stored_path) && Storage::disk($disk)->exists($file->stored_path)) {
                Storage::disk($disk)->delete($file->stored_path);
            }

            $file->deleted_at = now();
            $file->save();
            $cleaned++;
        }

        $modeLabel = $dryRun ? 'ditemukan (dry-run)' : 'dibersihkan';
        $this->info("Selesai: {$cleaned} file PDF privat {$modeLabel} (retensi {$retentionDays} hari / pending > 48 jam).");

        return self::SUCCESS;
    }
}
