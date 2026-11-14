<?php

namespace App\Console\Commands;

use App\Services\GooglePlacesSyncService;
use Illuminate\Console\Command;

class SyncUsuCopyShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shops:sync-usu 
                            {--query=fotokopi percetakan sekitar USU Medan : Query pencarian SerpApi Google Maps Engine}
                            {--radius=2500 : Radius pencarian dalam meter dari pusat kampus USU}
                            {--dry-run : Pratinjau data tanpa menyimpan perubahan ke database}
                            {--api-key= : Gunakan SerpApi Key spesifik}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data tempat fotokopi & percetakan di sekitar kampus USU via SerpApi Google Maps Engine dengan pencocokan Haversine dan proteksi data manual';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = (string)$this->option('query');
        $radius = (float)$this->option('radius');
        $dryRun = (bool)$this->option('dry-run');
        $apiKey = $this->option('api-key');

        $this->newLine();
        $this->info("===================================================================");
        $this->info("   SINKRONISASI USAHA FOTOKOPI & PERCETAKAN AREA USU MEDAN        ");
        $this->info("===================================================================");
        $this->line("• Query Pencarian : <comment>{$query}</comment>");
        $this->line("• Titik Pusat     : <comment>3.5629, 98.6562 (Kampus Padang Bulan USU)</comment>");
        $this->line("• Radius          : <comment>{$radius} meter (Dr. Mansyur, Jamin Ginting, Setia Budi)</comment>");
        $this->line("• Mode Eksekusi   : " . ($dryRun ? "<fg=yellow;options=bold>DRY-RUN (Simulasi / Tidak Mengubah Database)</>" : "<fg=green;options=bold>LIVE SYNC (Menyimpan Perubahan)</>"));
        $this->newLine();

        $service = new GooglePlacesSyncService(
            apiKey: $apiKey,
            centerLat: 3.5629,
            centerLng: 98.6562,
            searchRadius: $radius
        );

        $this->info("Sedang menghubungi SerpApi Google Maps Engine & memproses pemetaan...");
        $stats = $service->sync($query, $dryRun);

        // Display results table
        $tableRows = [];
        foreach ($stats['items'] as $idx => $item) {
            $actionLabel = match ($item['action']) {
                'matched_id' => '<fg=cyan>Match (Place ID)</>',
                'matched_name' => '<fg=green>Match (Nama Toko)</>',
                'matched_name_fuzzy' => '<fg=green>Match (Nama Serupa)</>',
                'matched_haversine' => '<fg=green>Match (Haversine ' . ($item['haversine_distance_m'] ?? '0') . 'm)</>',
                'created' => '<fg=yellow>New Place</>',
                default => $item['action'],
            };

            $servicesStr = !empty($item['detected_services']) 
                ? implode(', ', array_slice($item['detected_services'], 0, 3)) . (count($item['detected_services']) > 3 ? '...' : '')
                : '-';

            $tableRows[] = [
                $idx + 1,
                $item['name'],
                $item['address'],
                "⭐ {$item['rating']}",
                $actionLabel,
                $servicesStr,
            ];
        }

        $this->table(
            ['#', 'Nama Usaha', 'Alamat Ringkas', 'Rating', 'Status Pemetaan', 'Layanan Terdeteksi (NLP)'],
            $tableRows
        );

        $this->newLine();
        $this->info("-------------------------------------------------------------------");
        $this->info("RINGKASAN SINKRONISASI:");
        $this->line("• Total Tempat Terjaring    : <options=bold>{$stats['total_fetched']}</>");
        $this->line("• Cocok via Google Place ID : <options=bold>{$stats['matched_by_id']}</>");
        $this->line("• Cocok via Haversine (<30m): <options=bold>{$stats['matched_by_haversine']}</>");
        $this->line("• Tempat Baru Dibuat        : <options=bold>{$stats['created_new']}</>");
        $this->line("• Tag Layanan Terdeteksi    : <options=bold>{$stats['services_tagged']}</>");
        $this->line("• Ulasan Google Disimpan    : <options=bold>{$stats['reviews_saved']}</>");
        $this->info("-------------------------------------------------------------------");

        if ($dryRun) {
            $this->warn("Catatan: Perintah dijalankan dalam mode --dry-run. Jalankan tanpa flag --dry-run untuk menyimpan perubahan.");
        } else {
            $this->info("✅ Sinkronisasi berhasil! Data kontak manual dan katalog item tetap terlindungi.");
        }

        return Command::SUCCESS;
    }
}

