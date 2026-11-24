<?php

namespace App\Console\Commands;

use App\Services\Routing\CampusGraphBuilderService;
use Illuminate\Console\Command;

class SyncCampusRoutingGraphCommand extends Command
{
    protected $signature = 'routing:sync-campus-graph
                            {--from-snapshot : Muat dan kompilasi statistik ulang dari snapshot lokal tanpa query Overpass}';

    protected $description = 'Akuisisi jaringan jalan pejalan kaki, poligon bangunan, dan hambatan Kampus USU dari Overpass API lalu simpan ke runtime & Git snapshot';

    public function handle(CampusGraphBuilderService $builder): int
    {
        $fromSnapshot = (bool) $this->option('from-snapshot');
        $this->info($fromSnapshot
            ? 'Memuat graf routing Kampus USU dari snapshot lokal...'
            : 'Mengakuisisi jaringan jalan kaki & hambatan Kampus USU dari OpenStreetMap Overpass API...');

        try {
            $graph = $builder->buildAndSave($fromSnapshot);
            $stats = $graph['stats'] ?? [];
            $conn = $stats['connectivity'] ?? [];

            $this->info('Graf routing Kampus USU berhasil disimpan!');
            $this->table(
                ['Metrik Graf & Hambatan', 'Nilai'],
                [
                    ['Sumber Data', $stats['source'] ?? '-'],
                    ['Total Simpul (Nodes)', $stats['node_count'] ?? 0],
                    ['Total Segmen Jalan (Edges)', $stats['edge_count'] ?? 0],
                    ['Segmen Pejalan Kaki (Walkable)', $stats['walkable_edge_count'] ?? 0],
                    ['Segmen Kendaraan (Drivable)', $stats['drivable_edge_count'] ?? 0],
                    ['Poligon Bangunan (Anti-Tembus)', $stats['building_polygon_count'] ?? 0],
                    ['Garis Hambatan Parit/Pagar', $stats['barrier_count'] ?? 0],
                    ['Pulau Terisolasi Dibersihkan', ($stats['removed_isolated_islands'] ?? 0) . ' pulau (' . ($stats['removed_isolated_nodes'] ?? 0) . ' node)'],
                    ['Konektivitas Gedung DB', ($conn['buildings_connected'] ?? 0) . ' / ' . ($conn['buildings_total'] ?? 0)],
                    ['Konektivitas Fotokopi dalam BBox', ($conn['places_in_bbox_connected'] ?? 0) . ' (dari ' . ($conn['places_total'] ?? 0) . ' total)'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal membangun graf kampus: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
