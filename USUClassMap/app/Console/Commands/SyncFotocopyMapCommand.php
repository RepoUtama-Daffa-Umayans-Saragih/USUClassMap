<?php

namespace App\Console\Commands;

use App\Observers\GitSnapshotObserver;
use App\Services\Fotocopy\StoreMapSyncService;
use Illuminate\Console\Command;

class SyncFotocopyMapCommand extends Command
{
    protected $signature = 'fotocopy:sync-map';

    protected $description = 'Sinkronisasi 1:1 seluruh toko Admin (fotocopy_stores) ke Peta & Direktori Publik (photocopy_places) beserta katalognya';

    public function handle(StoreMapSyncService $syncService): int
    {
        $stats = [];
        GitSnapshotObserver::withoutSync(function () use ($syncService, &$stats): void {
            $stats = $syncService->syncAll();
        });

        $this->info('Sinkronisasi Admin Toko <-> USU Maps selesai:');
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['fotocopy_stores (Admin)', $stats['stores_count'] ?? 0],
                ['photocopy_places (Peta / Publik)', $stats['places_count'] ?? 0],
                ['fotocopy_services', $stats['services_count'] ?? 0],
                ['photocopy_items', $stats['items_count'] ?? 0],
                ['Place Baru Dibuat', $stats['created_places'] ?? 0],
                ['Place Diperbarui', $stats['updated_places'] ?? 0],
                ['Store Baru dari Orphan Place', $stats['created_stores'] ?? 0],
                ['Item Katalog Disinkronkan', $stats['items_synced'] ?? 0],
                ['Service Baru dari Orphan Item', $stats['services_created'] ?? 0],
            ]
        );

        return self::SUCCESS;
    }
}
