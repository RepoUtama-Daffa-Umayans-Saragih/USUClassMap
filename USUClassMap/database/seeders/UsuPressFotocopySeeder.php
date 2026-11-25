<?php

namespace Database\Seeders;

use App\Models\User;
use App\Observers\GitSnapshotObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuPressFotocopySeeder extends Seeder
{
    public const USUPRESS_SLUG = 'usu-press-penerbit-fotocopy';
    public const USUPRESS_NAME = 'USU Press (USUpress Penerbit & Fotocopy)';

    public const ADMIN_EMAIL = 'admin.usupress@usu.ac.id';
    public const ADMIN_NIM = 'adminusupress';

    public const CASHIER_EMAIL = 'kasir.usupress@usu.ac.id';
    public const CASHIER_NIM = 'kasirusupress';

    public const DEFAULT_PASSWORD = 'usupress123';

    public const WHATSAPP_DIGITS = '628116180409';
    public const PHONE_INTL = '+62 811-6180-409';
    public const PHONE_NATIONAL = '0811-6180-409';

    /**
     * Seeder idempoten untuk toko mitra resmi `USU Press (USUpress Penerbit & Fotocopy)`
     * di sekitar Gedung USUpress / Fasilkom-TI USU, lengkap dengan sinkronisasi dual-table
     * (`fotocopy_stores` <-> `photocopy_places`, `fotocopy_services` <-> `photocopy_items`),
     * katalog penerbitan + jasa fotokopi/print + ATK, serta akun Admin & Kasir terisolasi.
     */
    public function run(): void
    {
        $catalogPath = database_path('seeders/data/master_fotocopy_catalog.json');
        $catalog = [];

        if (is_file($catalogPath)) {
            $decoded = json_decode((string) file_get_contents($catalogPath), true);
            if (is_array($decoded)) {
                $catalog = $decoded;
            }
        }

        $selectedSkus = $this->selectCuratedUsuPressSkus($catalog);
        $desiredRows = array_merge(
            $this->buildSignaturePublishingItems(),
            $this->buildPayloads($selectedSkus)
        );
        $startingPrice = !empty($desiredRows) ? min(array_column($desiredRows, 'price')) : 250;

        $summary = [];

        GitSnapshotObserver::withoutSync(function () use ($desiredRows, $startingPrice, &$summary): void {
            DB::transaction(function () use ($desiredRows, $startingPrice, &$summary): void {
                $now = now()->format('Y-m-d H:i:s');

                // Ambil koordinat akurat dari gedung USUPRESS di tabel buildings jika tersedia
                $usupressBuilding = DB::table('buildings')->where('code', 'USUPRESS')->first();
                $lat = ($usupressBuilding && is_numeric($usupressBuilding->latitude))
                    ? (float) $usupressBuilding->latitude
                    : 3.5622266;
                $lng = ($usupressBuilding && is_numeric($usupressBuilding->longitude))
                    ? (float) $usupressBuilding->longitude
                    : 98.6589566;

                $mapsUrl = "https://maps.google.com/?q={$lat},{$lng}";
                $locationName = 'Jl. Universitas No. 9, Dekat Gedung Fasilkom-TI USU, Padang Bulan, Medan';
                $description = 'Unit Penerbitan Resmi & Mitra Fotokopi Universitas Sumatera Utara (USU Press). '
                    . 'Melayani cetak & penerbitan buku ISBN, modul kuliah, jilid hardcover skripsi/tesis, '
                    . 'layout naskah ilmiah, print warna, fotokopi kilat, scan PDF, dan ATK mahasiswa Fasilkom-TI.';

                // 1. Upsert `fotocopy_stores` berdasarkan slug permanen
                $existingStore = DB::table('fotocopy_stores')->where('slug', self::USUPRESS_SLUG)->first();
                $storePayload = [
                    'user_id' => $existingStore?->user_id,
                    'name' => self::USUPRESS_NAME,
                    'slug' => self::USUPRESS_SLUG,
                    'location_name' => $locationName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'opening_hours' => '08:00 - 17:00',
                    'operating_days' => 'Senin - Sabtu',
                    'phone' => self::PHONE_INTL,
                    'whatsapp' => self::WHATSAPP_DIGITS,
                    'image_url' => $existingStore?->image_url ?: '/images/stores/preset-campus-press.webp',
                    'google_maps_url' => $mapsUrl,
                    'google_place_id' => null,
                    'instagram' => null,
                    'facebook' => null,
                    'description' => $description,
                    'rating' => 4.9,
                    'user_rating_count' => 48,
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

                // 2. Upsert akun Admin & Kasir USU Press terikat ke `$storeId`
                $passwordHash = Hash::make(self::DEFAULT_PASSWORD);

                $adminUser = User::updateOrCreate(
                    ['email' => self::ADMIN_EMAIL],
                    [
                        'name' => 'Admin USU Press',
                        'nim' => self::ADMIN_NIM,
                        'semester' => 'Staff Mitra',
                        'password' => $passwordHash,
                        'role' => 'admin_fotokopi',
                        'store_id' => $storeId,
                    ]
                );

                $cashierUser = User::updateOrCreate(
                    ['email' => self::CASHIER_EMAIL],
                    [
                        'name' => 'Kasir USU Press',
                        'nim' => self::CASHIER_NIM,
                        'semester' => 'Kasir Mitra',
                        'password' => $passwordHash,
                        'role' => 'cashier_fotokopi',
                        'store_id' => $storeId,
                    ]
                );

                DB::table('fotocopy_stores')
                    ->where('id', $storeId)
                    ->update(['user_id' => $adminUser->id, 'updated_at' => $now]);

                // 3. Upsert `photocopy_places` berdasarkan slug permanen
                $existingPlace = DB::table('photocopy_places')->where('slug', self::USUPRESS_SLUG)->first();
                $placePayload = [
                    'name' => self::USUPRESS_NAME,
                    'slug' => self::USUPRESS_SLUG,
                    'description' => $description,
                    'location_name' => $locationName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'entrance_lat' => $lat,
                    'entrance_lng' => $lng,
                    'google_maps_url' => $mapsUrl,
                    'google_maps_uri' => $mapsUrl,
                    'open_time' => '08:00',
                    'close_time' => '17:00',
                    'operating_days' => 'Senin - Sabtu',
                    'phone' => self::PHONE_INTL,
                    'phone_national' => self::PHONE_NATIONAL,
                    'phone_international' => self::PHONE_INTL,
                    'whatsapp' => self::WHATSAPP_DIGITS,
                    'whatsapp_url' => 'https://wa.me/' . self::WHATSAPP_DIGITS,
                    'instagram' => null,
                    'facebook' => null,
                    'image_url' => $existingStore?->image_url ?: '/images/stores/preset-campus-press.webp',
                    'rating' => 4.9,
                    'user_rating_count' => 48,
                    'is_active' => 1,
                    'business_status' => 'OPERATIONAL',
                    'is_verified' => 1,
                    'is_manually_verified' => 1,
                    'last_synced_at' => $now,
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

                // 4. Sinkronisasi katalog identik ke `fotocopy_services` & `photocopy_items`
                $srvStats = $this->syncTableRows('fotocopy_services', 'store_id', $storeId, $desiredRows, $now);
                $itmStats = $this->syncTableRows('photocopy_items', 'photocopy_place_id', $placeId, $desiredRows, $now);

                // 5. Tautkan master `services` unggulan ke `photocopy_place_service`
                $coreServiceIds = DB::table('services')->whereIn('id', range(1, 10))->pluck('id')->all();
                $existingPivotIds = DB::table('photocopy_place_service')
                    ->where('photocopy_place_id', $placeId)
                    ->pluck('service_id')
                    ->all();

                $pivotAttached = 0;
                foreach ($coreServiceIds as $serviceId) {
                    if (!in_array($serviceId, $existingPivotIds, true)) {
                        DB::table('photocopy_place_service')->insert([
                            'photocopy_place_id' => $placeId,
                            'service_id' => $serviceId,
                            'starting_price' => $startingPrice,
                            'unit' => 'lembar',
                            'notes' => 'Layanan resmi USU Press (USUpress Penerbit & Fotocopy)',
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
                    'lat' => $lat,
                    'lng' => $lng,
                    'admin_email' => $adminUser->email,
                    'cashier_email' => $cashierUser->email,
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
                    ['Nama Toko', self::USUPRESS_NAME],
                    ['Slug', self::USUPRESS_SLUG],
                    ['ID fotocopy_stores / photocopy_places', "{$summary['store_id']} / {$summary['place_id']}"],
                    ['Koordinat USUPRESS', "{$summary['lat']}, {$summary['lng']}"],
                    ['Admin / Kasir', "{$summary['admin_email']} / {$summary['cashier_email']}"],
                    ['Total Katalog', "{$summary['catalog_count']} item (penerbitan + jasa + ATK)"],
                    ['Diff fotocopy_services (+ / ~ / -)', "+{$srv['inserted']} / ~{$srv['updated']} / -{$srv['deleted']}"],
                    ['Diff photocopy_items (+ / ~ / -)', "+{$itm['inserted']} / ~{$itm['updated']} / -{$itm['deleted']}"],
                    ['Master Services Pivot Baru', (string) $summary['pivot_attached']],
                ]
            );
        }
    }

    /**
     * 4 layanan unggulan penerbitan & akademik khas USU Press dengan gambar valid di disk.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildSignaturePublishingItems(): array
    {
        return [
            [
                'name' => 'Cetak & Penerbitan Buku ISBN (Softcover A5/B5)',
                'category' => 'Print',
                'item_type' => 'jasa',
                'sub_category' => 'Penerbitan Buku',
                'brand' => 'USU Press',
                'pack_qty' => 1,
                'unit' => 'eksemplar',
                'price' => 35000,
                'description' => 'Layanan cetak buku monograf, buku ajar, dan prosiding ber-ISBN standar penerbitan USU Press dengan kertas bookpaper/HVS dan finishing softcover laminating doff.',
                'image_path' => $this->resolveExistingCatalogImage('fotocopy_catalog/jilid-softcover-direct-binding.webp'),
                'metadata' => json_encode([
                    'merk' => 'USU Press',
                    'tipe' => 'Penerbitan Buku ISBN',
                    'ukuran_atau_gramasi' => 'A5 / B5 (Bookpaper 70gsm / HVS 80gsm)',
                    'penjualan' => 'Per Eksemplar',
                    'sku_ref' => 'UPR-PUB-001',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => null,
                'is_available' => 1,
            ],
            [
                'name' => 'Jilid Hardcover Skripsi & Tesis USU Press',
                'category' => 'Jilid',
                'item_type' => 'jasa',
                'sub_category' => 'Penjilidan Akademik',
                'brand' => 'USU Press',
                'pack_qty' => 1,
                'unit' => 'buku',
                'price' => 45000,
                'description' => 'Penjilidan hardcover lux dengan hot-print tinta emas/perak sesuai standar tata tulis tugas akhir, skripsi, tesis, dan disertasi Universitas Sumatera Utara.',
                'image_path' => $this->resolveExistingCatalogImage('fotocopy_catalog/jilid-hardcover-skripsi-tinta-emas.webp'),
                'metadata' => json_encode([
                    'merk' => 'USU Press',
                    'tipe' => 'Hardcover Tinta Emas',
                    'ukuran_atau_gramasi' => 'A4 Standar Akademik USU',
                    'penjualan' => 'Per Buku',
                    'sku_ref' => 'UPR-JLD-002',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => null,
                'is_available' => 1,
            ],
            [
                'name' => 'Jasa Layout Naskah Buku & Jurnal Ilmiah',
                'category' => 'Digital',
                'item_type' => 'jasa',
                'sub_category' => 'Layout & Pracetak',
                'brand' => 'USU Press',
                'pack_qty' => 1,
                'unit' => 'halaman',
                'price' => 5000,
                'description' => 'Penataan tipografi, margin, daftar isi otomatis, header/footer bab, dan kesiapan pracetak PDF/X untuk buku ajar maupun manuskrip jurnal ilmiah.',
                'image_path' => $this->resolveExistingCatalogImage('fotocopy_catalog/jasa-rapikan-margin-daftar-isi-skripsi.webp'),
                'metadata' => json_encode([
                    'merk' => 'USU Press',
                    'tipe' => 'Layout & Pracetak',
                    'penjualan' => 'Per Halaman',
                    'sku_ref' => 'UPR-DIG-003',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => null,
                'is_available' => 1,
            ],
            [
                'name' => 'Cetak Modul Kuliah & Buku Ajar A5/B5',
                'category' => 'Print',
                'item_type' => 'jasa',
                'sub_category' => 'Cetak Buku & Modul',
                'brand' => 'USU Press',
                'pack_qty' => 1,
                'unit' => 'eksemplar',
                'price' => 18000,
                'description' => 'Cetak modul praktikum, diktat kuliah, dan handbook fakultas ukuran ringkas A5/B5 lengkap dengan sampul warna art carton 230gsm.',
                'image_path' => $this->resolveExistingCatalogImage('fotocopy_catalog/print-hitam-putih-booklet-a5.webp'),
                'metadata' => json_encode([
                    'merk' => 'USU Press',
                    'tipe' => 'Cetak Booklet & Modul',
                    'ukuran_atau_gramasi' => 'A5 / B5 HVS 80gsm + Cover Art Carton',
                    'penjualan' => 'Per Eksemplar',
                    'sku_ref' => 'UPR-PRT-004',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => null,
                'is_available' => 1,
            ],
        ];
    }

    /**
     * Pilih katalog kurasi (~60+ SKU) dari master catalog JSON: seluruh jasa + core SKUs + ATK pilihan.
     *
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    protected function selectCuratedUsuPressSkus(array $catalog): array
    {
        if (empty($catalog)) {
            return [];
        }

        $pairGroups = [];
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['pair_group'])) {
                $pairGroups[$skuRow['pair_group']][] = $skuRow;
            }
        }

        $hardCap = 72;
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

        // 1. Seluruh jasa fotokopi/print/jilid/finishing/digital agar semua alur pemesanan dokumen tersedia
        foreach ($catalog as $skuRow) {
            if (($skuRow['item_type'] ?? '') === 'jasa') {
                $addWithPairs($skuRow, false);
            }
        }

        // 2. Seluruh SKU core (kertas HVS, pulpen, map, materai) beserta pair_group
        foreach ($catalog as $skuRow) {
            if (!empty($skuRow['core'])) {
                $addWithPairs($skuRow, true);
            }
        }

        // 3. Lengkapi dengan ATK pendukung populer hingga target cap ~72 SKU master
        $extraAtk = array_values(array_filter(
            $catalog,
            fn (array $row): bool => !isset($selectedBySku[$row['sku']])
        ));
        usort($extraAtk, function (array $a, array $b): int {
            $ha = $this->h(self::USUPRESS_SLUG . '|' . $a['sku']);
            $hb = $this->h(self::USUPRESS_SLUG . '|' . $b['sku']);

            return $ha === $hb ? strcmp($a['sku'], $b['sku']) : ($ha <=> $hb);
        });

        foreach ($extraAtk as $skuRow) {
            if (count($selectedBySku) >= $hardCap) {
                break;
            }
            $addWithPairs($skuRow, true);
        }

        return array_values($selectedBySku);
    }

    /**
     * Bentuk payload kolom standar katalog dari daftar SKU master.
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
            $price = (int) $priceOpts[$this->h(self::USUPRESS_SLUG . "|{$sku}|price") % count($priceOpts)];

            $stock = null;
            if (($skuRow['item_type'] ?? '') === 'barang' && is_array($skuRow['stock_range'] ?? null)) {
                $minStock = (int) $skuRow['stock_range'][0];
                $maxStock = (int) $skuRow['stock_range'][1];
                $span = max(1, $maxStock - $minStock + 1);
                $stock = $minStock + ($this->h(self::USUPRESS_SLUG . "|{$sku}|stock") % $span);
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
                'image_path' => $this->resolveExistingCatalogImage((string) ($skuRow['image_path'] ?? '')),
                'metadata' => json_encode($skuRow['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => $stock,
                'is_available' => 1,
            ];
        }

        return $rows;
    }

    protected function resolveExistingCatalogImage(string $candidate): string
    {
        $clean = ltrim($candidate, '/');
        if ($clean !== '' && is_file(public_path('storage/' . $clean))) {
            return $clean;
        }

        return 'fotocopy_catalog/print-hitam-putih-a4-70gsm.webp';
    }

    protected function h(string $input): int
    {
        return (int) sprintf('%u', crc32($input));
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
