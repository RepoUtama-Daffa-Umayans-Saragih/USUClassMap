<?php

namespace Database\Seeders;

use App\Observers\GitSnapshotObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RichFotocopyCatalogSeeder extends Seeder
{
    protected const ARCHETYPES = [
        'skripsi',
        'atk_teknik',
        'print_kilat',
        'serba_ada',
    ];

    /**
     * Jalankan distribusi katalog realistis & deterministik ke seluruh 31 toko (dual-table).
     */
    public function run(): void
    {
        $catalogPath = database_path('seeders/data/master_fotocopy_catalog.json');
        if (!is_file($catalogPath)) {
            $this->command?->error("Master catalog tidak ditemukan di: {$catalogPath}");
            return;
        }

        $catalog = json_decode(file_get_contents($catalogPath), true);
        if (!is_array($catalog) || empty($catalog)) {
            $this->command?->error('Master catalog JSON kosong atau tidak valid.');
            return;
        }

        // Indeks pair_group agar item eceran & paket/grosir dari produk yang sama selalu ikut bersama
        $pairGroups = [];
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['pair_group'])) {
                $pairGroups[$skuRow['pair_group']][] = $skuRow;
            }
        }

        $stores = DB::table('fotocopy_stores')->orderBy('id')->get();
        if ($stores->isEmpty()) {
            $this->command?->error('Tidak ada data pada tabel fotocopy_stores.');
            return;
        }

        $placesBySlug = DB::table('photocopy_places')->get()->keyBy('slug');
        $placesByName = DB::table('photocopy_places')->get()->keyBy('name');

        $summaryRows = [];
        $totals = [
            'services_inserted' => 0,
            'services_updated' => 0,
            'services_deleted' => 0,
            'items_inserted' => 0,
            'items_updated' => 0,
            'items_deleted' => 0,
        ];

        GitSnapshotObserver::withoutSync(function () use (
            $stores,
            $placesBySlug,
            $placesByName,
            $catalog,
            $pairGroups,
            &$summaryRows,
            &$totals
        ): void {
            DB::transaction(function () use (
                $stores,
                $placesBySlug,
                $placesByName,
                $catalog,
                $pairGroups,
                &$summaryRows,
                &$totals
            ): void {
                $now = now()->format('Y-m-d H:i:s');

                foreach ($stores as $index => $store) {
                    $place = $placesBySlug->get($store->slug) ?? $placesByName->get($store->name);
                    if (!$place) {
                        $this->command?->error("Pasangan photocopy_places tidak ditemukan untuk toko #{$store->id} ({$store->slug}).");
                        continue;
                    }

                    $archetype = self::ARCHETYPES[$index % count(self::ARCHETYPES)];
                    $selectedSkus = $this->selectSkusForStore($store->slug, $archetype, $catalog, $pairGroups);
                    $desiredRows = $this->buildPayloads($store->slug, $archetype, $selectedSkus);

                    $srvStats = $this->syncTableRows('fotocopy_services', 'store_id', (int) $store->id, $desiredRows, $now);
                    $itmStats = $this->syncTableRows('photocopy_items', 'photocopy_place_id', (int) $place->id, $desiredRows, $now);

                    $totals['services_inserted'] += $srvStats['inserted'];
                    $totals['services_updated'] += $srvStats['updated'];
                    $totals['services_deleted'] += $srvStats['deleted'];
                    $totals['items_inserted'] += $itmStats['inserted'];
                    $totals['items_updated'] += $itmStats['updated'];
                    $totals['items_deleted'] += $itmStats['deleted'];

                    $summaryRows[] = [
                        $store->id . ' / ' . $place->id,
                        mb_strimwidth($store->name, 0, 34, '...'),
                        $archetype,
                        count($desiredRows),
                        "+{$srvStats['inserted']} / ~{$srvStats['updated']} / -{$srvStats['deleted']}",
                    ];
                }
            });
        });

        if ($this->command) {
            $this->command->table(
                ['Store / Place ID', 'Nama Toko', 'Arketipe', 'Total Item', 'Diff Services (+ / ~ / -)'],
                $summaryRows
            );
            $this->command->info(sprintf(
                'Selesai | fotocopy_services (+%d, ~%d, -%d) | photocopy_items (+%d, ~%d, -%d)',
                $totals['services_inserted'],
                $totals['services_updated'],
                $totals['services_deleted'],
                $totals['items_inserted'],
                $totals['items_updated'],
                $totals['items_deleted']
            ));
        }
    }

    /**
     * Hash deterministik non-negatif untuk pemilihan SKU, harga, dan stok.
     */
    protected function h(string $input): int
    {
        return (int) sprintf('%u', crc32($input));
    }

    /**
     * Pilih 62..105+ SKU secara deterministik per toko dengan menjamin seluruh core SKU & keutuhan pair_group.
     */
    protected function selectSkusForStore(string $slug, string $archetype, array $catalog, array $pairGroups): array
    {
        $target = 62 + ($this->h("{$slug}|target") % 44);
        $hardCap = 110;

        $selectedBySku = [];

        $addWithPairs = static function (array $skuRow, bool $enforceCap = false) use (&$selectedBySku, $pairGroups, $hardCap): void {
            $group = !empty($skuRow['pair_group']) && isset($pairGroups[$skuRow['pair_group']])
                ? $pairGroups[$skuRow['pair_group']]
                : [$skuRow];

            $toAdd = [];
            foreach ($group as $candidate) {
                if (!isset($selectedBySku[$candidate['sku']])) {
                    $toAdd[] = $candidate;
                }
            }

            if (empty($toAdd)) {
                return;
            }

            if ($enforceCap && (count($selectedBySku) + count($toAdd)) > $hardCap) {
                return;
            }

            foreach ($toAdd as $candidate) {
                $selectedBySku[$candidate['sku']] = $candidate;
            }
        };

        // 1. Wajib masukkan seluruh SKU core beserta pasangan pair_group-nya
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['core'])) {
                $addWithPairs($skuRow, false);
            }
        }

        // 2. Kelompokkan non-core: cocok arketipe vs lainnya
        $matching = [];
        $others = [];
        foreach ($catalog as $skuRow) {
            if (isset($selectedBySku[$skuRow['sku']])) {
                continue;
            }
            $archetypes = $skuRow['archetypes'] ?? [];
            if (in_array($archetype, $archetypes, true)) {
                $matching[] = $skuRow;
            } else {
                $others[] = $skuRow;
            }
        }

        $sorter = function (array $a, array $b) use ($slug): int {
            $ha = $this->h("{$slug}|{$a['sku']}");
            $hb = $this->h("{$slug}|{$b['sku']}");
            if ($ha === $hb) {
                return strcmp($a['sku'], $b['sku']);
            }
            return $ha <=> $hb;
        };

        usort($matching, $sorter);
        usort($others, $sorter);

        // Prioritaskan kecocokan arketipe hingga target (sisakan ruang minimal untuk variasi umum)
        $archetypeQuota = (int) round($target * 0.78);
        foreach ($matching as $skuRow) {
            if (count($selectedBySku) >= $archetypeQuota) {
                break;
            }
            $addWithPairs($skuRow, true);
        }

        // Lengkapi sampai target dengan variasi dari segmen lain + sisa matching
        foreach (array_merge($others, $matching) as $skuRow) {
            if (count($selectedBySku) >= $target) {
                break;
            }
            $addWithPairs($skuRow, true);
        }

        // Pastikan setiap toko minimal punya >=1 paket, >=1 niche, >=1 eceran, Print, Fotocopy, Jilid
        $hasSegment = ['niche' => false, 'paket' => false, 'eceran' => false];
        foreach ($selectedBySku as $row) {
            if (isset($hasSegment[$row['segment']])) {
                $hasSegment[$row['segment']] = true;
            }
        }
        foreach ($hasSegment as $seg => $present) {
            if (!$present) {
                foreach ($others as $skuRow) {
                    if ($skuRow['segment'] === $seg) {
                        $addWithPairs($skuRow, true);
                        break;
                    }
                }
            }
        }

        return array_values($selectedBySku);
    }

    /**
     * Bentuk payload kolom database untuk satu toko dari daftar SKU terpilih.
     */
    protected function buildPayloads(string $slug, string $archetype, array $selectedSkus): array
    {
        $rows = [];
        foreach ($selectedSkus as $skuRow) {
            $sku = $skuRow['sku'];
            $priceOpts = $skuRow['price_options'];
            if ($archetype === 'print_kilat' && in_array($skuRow['category'], ['Print', 'Fotocopy'], true)) {
                $price = (int) min($priceOpts);
            } else {
                $price = (int) $priceOpts[$this->h("{$slug}|{$sku}|price") % count($priceOpts)];
            }

            $stock = null;
            if ($skuRow['item_type'] === 'barang' && is_array($skuRow['stock_range'])) {
                $minStock = (int) $skuRow['stock_range'][0];
                $maxStock = (int) $skuRow['stock_range'][1];
                $span = max(1, $maxStock - $minStock + 1);
                $stock = $minStock + ($this->h("{$slug}|{$sku}|stock") % $span);
            }

            $rows[] = [
                'name' => (string) $skuRow['name'],
                'category' => (string) $skuRow['category'],
                'item_type' => (string) $skuRow['item_type'],
                'sub_category' => $skuRow['sub_category'],
                'brand' => $skuRow['brand'],
                'pack_qty' => (int) ($skuRow['pack_qty'] ?? 1),
                'unit' => (string) $skuRow['unit'],
                'price' => $price,
                'description' => (string) $skuRow['description'],
                'image_path' => (string) $skuRow['image_path'],
                'metadata' => json_encode($skuRow['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => $stock,
                'is_available' => 1,
            ];
        }

        return $rows;
    }

    /**
     * Sinkronisasi idempoten per toko/tabel berdasarkan `name`.
     *
     * @return array{inserted: int, updated: int, deleted: int}
     */
    protected function syncTableRows(string $table, string $fkCol, int $fkId, array $desiredRows, string $now): array
    {
        $existing = DB::table($table)->where($fkCol, $fkId)->get()->keyBy('name');
        $desiredByName = [];
        foreach ($desiredRows as $row) {
            $desiredByName[$row['name']] = $row;
        }

        // 1. Hapus item lama yang tidak lagi ada di katalog toko ini
        $idsToDelete = [];
        foreach ($existing as $name => $row) {
            if (!isset($desiredByName[$name])) {
                $idsToDelete[] = $row->id;
            }
        }
        $deleted = 0;
        foreach (array_chunk($idsToDelete, 200) as $chunk) {
            $deleted += DB::table($table)->whereIn('id', $chunk)->delete();
        }

        // 2. Perbarui yang berubah & kumpulkan baris baru
        $updated = 0;
        $toInsert = [];

        foreach ($desiredByName as $name => $payload) {
            $cur = $existing->get($name);
            if ($cur) {
                if ($this->rowDiffers($cur, $payload)) {
                    DB::table($table)
                        ->where('id', $cur->id)
                        ->update(array_merge($payload, ['updated_at' => $now]));
                    $updated++;
                }
            } else {
                $toInsert[] = array_merge([$fkCol => $fkId], $payload, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $inserted = count($toInsert);
        foreach (array_chunk($toInsert, 50) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    protected function rowDiffers(object $cur, array $desired): bool
    {
        return (string) $cur->category !== (string) $desired['category']
            || (string) ($cur->item_type ?? '') !== (string) $desired['item_type']
            || ($cur->sub_category ?? null) !== ($desired['sub_category'] ?? null)
            || ($cur->brand ?? null) !== ($desired['brand'] ?? null)
            || (int) ($cur->pack_qty ?? 1) !== (int) $desired['pack_qty']
            || (string) $cur->unit !== (string) $desired['unit']
            || (int) $cur->price !== (int) $desired['price']
            || (string) ($cur->description ?? '') !== (string) $desired['description']
            || ($cur->image_path ?? null) !== ($desired['image_path'] ?? null)
            || ($cur->metadata ?? null) !== ($desired['metadata'] ?? null)
            || ($cur->stock !== null ? (int) $cur->stock : null) !== ($desired['stock'] !== null ? (int) $desired['stock'] : null)
            || (int) $cur->is_available !== (int) $desired['is_available'];
    }
}
