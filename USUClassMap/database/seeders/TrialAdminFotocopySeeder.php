<?php

namespace Database\Seeders;

use App\Observers\GitSnapshotObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrialAdminFotocopySeeder extends Seeder
{
    public const TRIAL_SLUG = 'fotocopy-trial-admin';
    public const TRIAL_NAME = 'Fotocopy Trial Admin';
    public const TRIAL_WHATSAPP_DIGITS = '6285769737410';
    public const TRIAL_PHONE_INTL = '+62 857-6973-7410';
    public const TRIAL_PHONE_NATIONAL = '0857-6973-7410';

    /**
     * Jalankan pembuatan toko mitra trial `Fotocopy Trial Admin` (+62 857-6973-7410)
     * secara idempoten dan sinkron dual-table (`fotocopy_stores` & `photocopy_places`)
     * beserta katalog lengkap di `fotocopy_services` & `photocopy_items`.
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

        $selectedSkus = $this->selectCuratedTrialSkus($catalog);
        $desiredRows = $this->buildPayloads($selectedSkus);
        $startingPrice = !empty($desiredRows) ? min(array_column($desiredRows, 'price')) : 200;

        $summary = [];

        GitSnapshotObserver::withoutSync(function () use ($desiredRows, $startingPrice, &$summary): void {
            DB::transaction(function () use ($desiredRows, $startingPrice, &$summary): void {
                $now = now()->format('Y-m-d H:i:s');

                // Resolusi akun pengelola (Admin Fotokopi -> Cashier Fotokopi -> Super Admin)
                $managerUser = DB::table('users')->where('email', 'adminfotokopi@usu.ac.id')->first()
                    ?? DB::table('users')->where('role', 'admin_fotokopi')->first()
                    ?? DB::table('users')->where('role', 'superadmin')->first();

                $managerUserId = $managerUser?->id;

                $lat = 3.562680;
                $lng = 98.659120;
                $mapsUrl = "https://maps.google.com/?q={$lat},{$lng}";
                $locationName = 'Depan Gedung Fasilkom-TI USU, Jl. Universitas No. 9A, Padang Bulan, Medan';
                $description = 'Mitra fotokopi resmi untuk uji coba alur pemesanan multi-PDF, catatan per dokumen, '
                    . 'pembayaran digital, invoice PDF otomatis, dan pengiriman file otomatis ke WhatsApp operator.';

                // 1. Upsert `fotocopy_stores` berdasarkan `slug`
                $existingStore = DB::table('fotocopy_stores')->where('slug', self::TRIAL_SLUG)->first();
                $storePayload = [
                    'user_id' => $managerUserId,
                    'name' => self::TRIAL_NAME,
                    'slug' => self::TRIAL_SLUG,
                    'location_name' => $locationName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'opening_hours' => '07:30 - 22:00',
                    'operating_days' => 'Senin - Minggu',
                    'phone' => self::TRIAL_PHONE_INTL,
                    'whatsapp' => self::TRIAL_WHATSAPP_DIGITS,
                    'image_url' => $existingStore?->image_url ?: '/images/stores/preset-modern-copy.webp',
                    'google_maps_url' => $mapsUrl,
                    'google_place_id' => null,
                    'instagram' => null,
                    'facebook' => null,
                    'description' => $description,
                    'rating' => 5.0,
                    'user_rating_count' => 1,
                    'is_open' => 1,
                    'starting_price' => $startingPrice,
                ];

                if ($existingStore) {
                    $storeId = (int) $existingStore->id;
                    DB::table('fotocopy_stores')
                        ->where('id', $storeId)
                        ->update(array_merge($storePayload, ['updated_at' => $now]));
                } else {
                    $storeId = (int) DB::table('fotocopy_stores')->insertGetId(array_merge($storePayload, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }

                // 2. Upsert `photocopy_places` berdasarkan `slug`
                $existingPlace = DB::table('photocopy_places')->where('slug', self::TRIAL_SLUG)->first();
                $placePayload = [
                    'name' => self::TRIAL_NAME,
                    'slug' => self::TRIAL_SLUG,
                    'description' => $description,
                    'location_name' => $locationName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'google_maps_url' => $mapsUrl,
                    'google_maps_uri' => $mapsUrl,
                    'open_time' => '07:30',
                    'close_time' => '22:00',
                    'operating_days' => 'Senin - Minggu',
                    'phone' => self::TRIAL_PHONE_INTL,
                    'phone_national' => self::TRIAL_PHONE_NATIONAL,
                    'phone_international' => self::TRIAL_PHONE_INTL,
                    'whatsapp' => self::TRIAL_WHATSAPP_DIGITS,
                    'whatsapp_url' => 'https://wa.me/' . self::TRIAL_WHATSAPP_DIGITS,
                    'instagram' => null,
                    'facebook' => null,
                    'image_url' => $existingStore?->image_url ?: '/images/stores/preset-modern-copy.webp',
                    'rating' => 5.0,
                    'user_rating_count' => 1,
                    'is_active' => 1,
                    'business_status' => 'OPERATIONAL',
                    'is_verified' => 1,
                    'is_manually_verified' => 1,
                    'last_synced_at' => $existingPlace?->last_synced_at ?? $now,
                ];

                if ($existingPlace) {
                    $placeId = (int) $existingPlace->id;
                    DB::table('photocopy_places')
                        ->where('id', $placeId)
                        ->update(array_merge($placePayload, ['updated_at' => $now]));
                } else {
                    $placeId = (int) DB::table('photocopy_places')->insertGetId(array_merge($placePayload, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }

                // 3. Sinkronisasi katalog identik ke `fotocopy_services` & `photocopy_items`
                $srvStats = $this->syncTableRows('fotocopy_services', 'store_id', $storeId, $desiredRows, $now);
                $itmStats = $this->syncTableRows('photocopy_items', 'photocopy_place_id', $placeId, $desiredRows, $now);

                // 4. Hubungkan master `services` unggulan secara idempoten ke `photocopy_place_service`
                $coreServiceIds = DB::table('services')->whereIn('id', range(1, 10))->pluck('id')->all();
                $existingPivotServiceIds = DB::table('photocopy_place_service')
                    ->where('photocopy_place_id', $placeId)
                    ->pluck('service_id')
                    ->all();

                $pivotAttached = 0;
                foreach ($coreServiceIds as $serviceId) {
                    if (!in_array($serviceId, $existingPivotServiceIds, true)) {
                        DB::table('photocopy_place_service')->insert([
                            'photocopy_place_id' => $placeId,
                            'service_id' => $serviceId,
                            'starting_price' => $startingPrice,
                            'unit' => 'lembar',
                            'notes' => 'Layanan trial resmi Fotocopy Trial Admin',
                            'is_auto_detected' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $pivotAttached++;
                    }
                }

                $summary = [
                    'store_id' => $storeId,
                    'place_id' => $placeId,
                    'manager_user_id' => $managerUserId,
                    'manager_email' => $managerUser?->email ?? '-',
                    'whatsapp' => self::TRIAL_WHATSAPP_DIGITS,
                    'phone' => self::TRIAL_PHONE_INTL,
                    'catalog_count' => count($desiredRows),
                    'srv_stats' => $srvStats,
                    'itm_stats' => $itmStats,
                    'pivot_attached' => $pivotAttached,
                ];
            });
        });

        if ($this->command && !empty($summary)) {
            $srv = $summary['srv_stats'];
            $itm = $summary['itm_stats'];
            $this->command->table(
                ['Properti', 'Nilai'],
                [
                    ['Nama Toko', self::TRIAL_NAME],
                    ['Slug', self::TRIAL_SLUG],
                    ['ID fotocopy_stores / photocopy_places', "{$summary['store_id']} / {$summary['place_id']}"],
                    ['Pengelola (user_id)', "#{$summary['manager_user_id']} ({$summary['manager_email']})"],
                    ['Phone / WhatsApp', "{$summary['phone']} / {$summary['whatsapp']}"],
                    ['Total Katalog', "{$summary['catalog_count']} item (jasa + ATK)"],
                    ['Diff fotocopy_services (+ / ~ / -)', "+{$srv['inserted']} / ~{$srv['updated']} / -{$srv['deleted']}"],
                    ['Diff photocopy_items (+ / ~ / -)', "+{$itm['inserted']} / ~{$itm['updated']} / -{$itm['deleted']}"],
                    ['Master Services Pivot Baru', (string) $summary['pivot_attached']],
                ]
            );
        }
    }

    /**
     * Hash deterministik non-negatif untuk pemilihan harga & stok trial.
     */
    protected function h(string $input): int
    {
        return (int) sprintf('%u', crc32($input));
    }

    /**
     * Pilih katalog kurasi lengkap (~100 SKU):
     * - Seluruh layanan jasa (Print, Fotocopy, Jilid, Laminating, Scan, Digital)
     * - Seluruh SKU core + keutuhan pair_group (eceran + paket/lusinan)
     * - ATK pendukung & item niche (Materai 10.000, Flashdisk, Map, Binder, Kertas)
     *
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    protected function selectCuratedTrialSkus(array $catalog): array
    {
        $pairGroups = [];
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['pair_group'])) {
                $pairGroups[$skuRow['pair_group']][] = $skuRow;
            }
        }

        $hardCap = 108;
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

        // 1. Seluruh core SKU + pasangan pair_group
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['core'])) {
                $addWithPairs($skuRow, false);
            }
        }

        // 2. Seluruh jasa cetak/fotokopi/jilid/finishing/digital agar pengujian multi-PDF mencakup semua opsi layanan
        foreach ($catalog as $skuRow) {
            if (($skuRow['item_type'] ?? '') === 'jasa') {
                $addWithPairs($skuRow, false);
            }
        }

        // 3. Lengkapi dengan ATK serba_ada, niche (Materai/Flashdisk), dan eceran/paket hingga target
        $priorityAtk = [];
        $otherAtk = [];
        foreach ($catalog as $skuRow) {
            if (isset($selectedBySku[$skuRow['sku']])) {
                continue;
            }
            $archetypes = $skuRow['archetypes'] ?? [];
            $segment = $skuRow['segment'] ?? '';
            if (in_array('serba_ada', $archetypes, true) || $segment === 'niche' || $segment === 'paket') {
                $priorityAtk[] = $skuRow;
            } else {
                $otherAtk[] = $skuRow;
            }
        }

        $sorter = function (array $a, array $b): int {
            $ha = $this->h(self::TRIAL_SLUG . '|' . $a['sku']);
            $hb = $this->h(self::TRIAL_SLUG . '|' . $b['sku']);
            return $ha === $hb ? strcmp($a['sku'], $b['sku']) : ($ha <=> $hb);
        };

        usort($priorityAtk, $sorter);
        usort($otherAtk, $sorter);

        foreach (array_merge($priorityAtk, $otherAtk) as $skuRow) {
            if (count($selectedBySku) >= 102) {
                break;
            }
            $addWithPairs($skuRow, true);
        }

        return array_values($selectedBySku);
    }

    /**
     * Bentuk payload kolom standar katalog dari daftar SKU terpilih.
     *
     * @param  array<int, array<string, mixed>>  $selectedSkus
     * @return array<int, array<string, mixed>>
     */
    protected function buildPayloads(array $selectedSkus): array
    {
        $rows = [];
        foreach ($selectedSkus as $skuRow) {
            $sku = (string) $skuRow['sku'];
            $priceOpts = $skuRow['price_options'];
            $price = (int) $priceOpts[$this->h(self::TRIAL_SLUG . "|{$sku}|price") % count($priceOpts)];

            $stock = null;
            if (($skuRow['item_type'] ?? '') === 'barang' && is_array($skuRow['stock_range'] ?? null)) {
                $minStock = (int) $skuRow['stock_range'][0];
                $maxStock = (int) $skuRow['stock_range'][1];
                $span = max(1, $maxStock - $minStock + 1);
                $stock = $minStock + ($this->h(self::TRIAL_SLUG . "|{$sku}|stock") % $span);
            }

            $rows[] = [
                'name' => (string) $skuRow['name'],
                'category' => (string) $skuRow['category'],
                'item_type' => (string) $skuRow['item_type'],
                'sub_category' => $skuRow['sub_category'] ?? null,
                'brand' => $skuRow['brand'] ?? null,
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
     * Sinkronisasi idempoten katalog ke tabel target berdasarkan `name`.
     *
     * @param  array<int, array<string, mixed>>  $desiredRows
     * @return array{inserted: int, updated: int, deleted: int}
     */
    protected function syncTableRows(string $table, string $fkCol, int $fkId, array $desiredRows, string $now): array
    {
        $existing = DB::table($table)->where($fkCol, $fkId)->get()->keyBy('name');
        $desiredByName = [];
        foreach ($desiredRows as $row) {
            $desiredByName[$row['name']] = $row;
        }

        // Jangan hapus item ekspansi katalog atau item kustom admin toko
        $deleted = 0;

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
