<?php

namespace App\Console\Commands;

use App\Services\GitSnapshotSyncService;
use Illuminate\Console\Command;

class GitSyncSnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:git-sync
                            {--export : Ekspor seluruh data CRUD saat ini ke database/seeders/snapshots/}
                            {--import : Impor data dari database/seeders/snapshots/ ke database lokal & Fuseki}
                            {--force : Paksa impor meskipun hash manifest sama dengan state lokal}
                            {--status : Tampilkan status sinkronisasi Git snapshot saat ini}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data CRUD (JSON/RDF) antara database lokal, Fuseki, dan folder Git snapshot (database/seeders/snapshots/)';

    /**
     * Execute the console command.
     */
    public function handle(GitSnapshotSyncService $syncService): int
    {
        $doExport = (bool) $this->option('export');
        $doImport = (bool) $this->option('import');
        $force = (bool) $this->option('force');
        $doStatus = (bool) $this->option('status') || (!$doExport && !$doImport);

        if ($doExport && $doImport) {
            $this->error('Gunakan salah satu opsi: --export atau --import (tidak bersamaan).');

            return self::FAILURE;
        }

        if ($doExport) {
            $this->info('Mengekspor seluruh data CRUD & RDF ke database/seeders/snapshots/ ...');
            $result = $syncService->exportAll(true);

            if (!($result['exported'] ?? false)) {
                $this->warn('Ekspor dilewati: ' . ($result['reason'] ?? 'unknown'));

                return self::FAILURE;
            }

            $this->info('Ekspor snapshot Git berhasil!');
            $this->line('Content Hash : ' . ($result['content_hash'] ?? '-'));
            $this->line('RDF Snapshot : ' . (($result['rdf_exported'] ?? false) ? 'Tersimpan (schedules.rdf)' : 'Gagal / tidak tersedia'));

            $rows = [];
            foreach ($result['counts'] ?? [] as $table => $count) {
                $rows[] = [$table, $count];
            }
            $this->table(['Entitas / Tabel', 'Jumlah Baris'], $rows);

            return self::SUCCESS;
        }

        if ($doImport) {
            $this->info($force
                ? 'Memaksa impor ulang seluruh snapshot Git ke database lokal & Fuseki ...'
                : 'Memeriksa dan mengimpor snapshot Git ke database lokal & Fuseki ...');

            $result = $syncService->importAll($force);

            if (!($result['synced'] ?? false)) {
                $reason = $result['reason'] ?? 'unknown';
                if ($reason === 'up_to_date') {
                    $this->info('Database lokal sudah sinkron dengan manifest.json. Gunakan --force untuk memaksa impor ulang.');

                    return self::SUCCESS;
                }
                if ($reason === 'no_manifest') {
                    $this->error('File database/seeders/snapshots/manifest.json tidak ditemukan. Jalankan --export terlebih dahulu.');

                    return self::FAILURE;
                }

                $this->warn("Impor dilewati: {$reason}");

                return self::FAILURE;
            }

            $this->info('Impor snapshot Git ke database lokal berhasil!');
            $this->line('Content Hash : ' . ($result['content_hash'] ?? '-'));
            $this->line('Fuseki RDF   : ' . (($result['fuseki_synced'] ?? false) ? 'Tersinkron ke Apache Jena Fuseki' : 'Offline / fallback (DB lokal tetap ter-update)'));
            $this->line('Kalibrasi 360: ' . ($result['calibrations_synced'] ?? 0) . ' entri dikirim ke Fuseki');

            $rows = [];
            foreach ($result['imported_counts'] ?? [] as $table => $count) {
                $pruned = $result['pruned_counts'][$table] ?? 0;
                $rows[] = [$table, $count, $pruned];
            }
            $this->table(['Tabel', 'Di-upsert', 'Di-prune'], $rows);

            return self::SUCCESS;
        }

        if ($doStatus) {
            $status = $syncService->getStatus();

            $this->info('=== STATUS AUTO-SYNC GIT SNAPSHOT ===');
            $this->line('Snapshot Tersedia  : ' . ($status['snapshot_exists'] ? 'Ya (database/seeders/snapshots/manifest.json)' : 'Belum ada'));
            $this->line('RDF Snapshot       : ' . ($status['rdf_snapshot_exists'] ? 'Ada (schedules.rdf)' : 'Belum ada'));
            $this->line('Kalibrasi Foto 360 : ' . $status['photo_360_calibrations_count'] . ' entri');
            $this->line('Tur Virtual FIB 360: ' . ($status['fib_walk_snapshot_exists'] ? ($status['fib_walk_scenes_count'] . ' scene aktif (fib_walk_graph.json)') : 'Belum ada'));
            $this->line('Manifest Hash      : ' . ($status['manifest_hash'] ?? '-'));
            $this->line('Manifest Dibuat    : ' . ($status['manifest_generated_at'] ?? '-'));
            $this->line('State Lokal Hash   : ' . ($status['local_hash'] ?? '-'));
            $this->line('Sync Lokal Terakhir: ' . ($status['local_synced_at'] ?? '-') . ($status['local_direction'] ? " ({$status['local_direction']})" : ''));

            if ($status['is_in_sync']) {
                $this->info('Status Sinkronisasi: SINKRON (DB lokal & manifest Git cocok)');
            } elseif ($status['has_pending_import']) {
                $this->warn('Status Sinkronisasi: PENDING IMPORT (manifest Git lebih baru dari state lokal)');
            } else {
                $this->warn('Status Sinkronisasi: BELUM TERINISIALISASI (jalankan: php artisan app:git-sync --export)');
            }

            $rows = [];
            foreach ($status['tables'] as $table => $info) {
                $rows[] = [
                    $table,
                    $info['db_count'] ?? 'N/A',
                    $info['snapshot_count'] ?? 'N/A',
                    $info['matches'] ? 'OK' : 'BEDA',
                ];
            }
            $this->table(['Tabel', 'DB Lokal', 'Snapshot Git', 'Status'], $rows);
        }

        return self::SUCCESS;
    }
}
