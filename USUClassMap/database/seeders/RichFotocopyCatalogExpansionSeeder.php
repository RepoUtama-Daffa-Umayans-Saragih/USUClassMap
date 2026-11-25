<?php

namespace Database\Seeders;

use App\Models\FotocopyStore;
use App\Observers\GitSnapshotObserver;
use App\Services\Fotocopy\StoreMapSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RichFotocopyCatalogExpansionSeeder extends Seeder
{
    /**
     * Seeder idempoten untuk memperkaya katalog ATK & Jasa Cetak di 25+ mitra fotokopi USU
     * (Pulpen Pink pastel/merk, Tipe-X kertas & cair, Stabilo pastel, Sticky Notes, Map,
     * perlengkapan kuliah harian, dan jasa cetak/jilid populer) dengan sinkronisasi 1:1
     * ke `fotocopy_services` & `photocopy_items`.
     */
    public function run(): void
    {
        $templates = $this->catalogTemplates();
        $totalInsertedServices = 0;
        $totalUpdatedServices = 0;
        $totalInsertedItems = 0;
        $totalUpdatedItems = 0;
        $storesProcessed = 0;

        GitSnapshotObserver::withoutSync(function () use (
            $templates,
            &$totalInsertedServices,
            &$totalUpdatedServices,
            &$totalInsertedItems,
            &$totalUpdatedItems,
            &$storesProcessed
        ): void {
            $syncService = app(StoreMapSyncService::class);
            $stores = FotocopyStore::where('is_open', true)->orderBy('id')->get();
            if ($stores->isEmpty()) {
                return;
            }

            $prioritySlugs = [
                'fotocopy-trial-admin',
                'usu-press-penerbit-fotocopy',
                'fotocopy-pak-budi-ft',
                'berkah-abadi-copy-atk-perpustakaan',
                'new-nasional-fotocopy',
            ];

            $priorityStores = $stores->filter(fn (FotocopyStore $s): bool => in_array((string) $s->slug, $prioritySlugs, true));
            $otherStores = $stores->reject(fn (FotocopyStore $s): bool => in_array((string) $s->slug, $prioritySlugs, true))->take(20);
            $targetStores = $priorityStores->concat($otherStores)->values();

            $now = now()->format('Y-m-d H:i:s');

            DB::transaction(function () use (
                $targetStores,
                $prioritySlugs,
                $templates,
                $syncService,
                $now,
                &$totalInsertedServices,
                &$totalUpdatedServices,
                &$totalInsertedItems,
                &$totalUpdatedItems,
                &$storesProcessed
            ): void {
                foreach ($targetStores as $store) {
                    $place = $syncService->resolvePlaceForStore($store) ?? $syncService->syncStoreToPlace($store);
                    $isFullCatalog = in_array((string) $store->slug, $prioritySlugs, true);

                    $chosenTemplates = $isFullCatalog
                        ? $templates
                        : $this->selectStoreTemplates($templates, (string) $store->slug);

                    $desiredRows = [];
                    foreach ($chosenTemplates as $tpl) {
                        $desiredRows[$tpl['name']] = $this->buildPayloadForStore($tpl, (string) $store->slug);
                    }

                    [$sIns, $sUpd] = $this->upsertCatalogRows('fotocopy_services', 'store_id', (int) $store->id, $desiredRows, $now);
                    [$iIns, $iUpd] = $this->upsertCatalogRows('photocopy_items', 'photocopy_place_id', (int) $place->id, $desiredRows, $now);

                    $totalInsertedServices += $sIns;
                    $totalUpdatedServices += $sUpd;
                    $totalInsertedItems += $iIns;
                    $totalUpdatedItems += $iUpd;
                    $storesProcessed++;
                }
            });
        });

        if ($this->command) {
            $this->command->table(
                ['Metrik Ekspansi Katalog', 'Nilai'],
                [
                    ['Toko Diperkaya', (string) $storesProcessed],
                    ['Total Template Produk/Jasa Baru', (string) count($templates)],
                    ['fotocopy_services (+ Baru / ~ Update)', "+{$totalInsertedServices} / ~{$totalUpdatedServices}"],
                    ['photocopy_items (+ Baru / ~ Update)', "+{$totalInsertedItems} / ~{$totalUpdatedItems}"],
                ]
            );
        }
    }

    /**
     * Daftar 42 produk ATK populer (termasuk Pulpen Pink & Tipe-X variatif) serta jasa cetak/jilid.
     * Setiap `image_path` telah diverifikasi eksis di disk `public/storage/fotocopy_catalog/`.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function catalogTemplates(): array
    {
        return [
            // === KELOMPOK 1: PULPEN PINK & PULPEN WARNA-WARNI (CORE) ===
            [
                'key' => 'pulpen-gel-pink-pastel',
                'always' => true,
                'name' => 'Pulpen Gel Pink Pastel 0.5mm (Eceran)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen & Pena Gel Pink',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [3000, 3500, 4000],
                'stock_range' => [18, 65],
                'image_path' => 'fotocopy_catalog/pulpen-gel-pink-pastel-05mm.webp',
                'description' => 'Pulpen gel warna pink pastel ujung jarum 0.5mm, tinta halus cepat kering untuk catatan kuliah estetis.',
                'metadata' => ['warna_varian' => 'Pink Pastel', 'ujung_mata' => '0.5mm Needle Tip', 'penjualan' => 'Eceran 1 pcs'],
            ],
            [
                'key' => 'pulpen-zebra-sarasa-pink',
                'always' => true,
                'name' => 'Pulpen Zebra Sarasa Clip 0.5mm Pink',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen & Pena Gel Pink',
                'brand' => 'Zebra',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [14000, 15000, 16000],
                'stock_range' => [8, 30],
                'image_path' => 'fotocopy_catalog/pulpen-zebra-sarasa-clip-05.webp',
                'description' => 'Pulpen Zebra Sarasa Clip original tinta pink cerah 0.5mm dengan klip pegas kokoh dan grip karet nyaman.',
                'metadata' => ['merk' => 'Zebra', 'warna_varian' => 'Pink', 'ujung_mata' => '0.5mm Retractable Gel'],
            ],
            [
                'key' => 'pulpen-joyko-qgel-pink',
                'always' => true,
                'name' => 'Pulpen Joyko GP-265 Q-Gel Pink 0.5mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen & Pena Gel Pink',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [3500, 4000, 4500],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/pulpen-gel-pink-pastel-05mm.webp',
                'description' => 'Pulpen gel Joyko GP-265 warna tinta pink 0.5mm terlaris mahasiswa, ekonomis dan anti bocor.',
                'metadata' => ['merk' => 'Joyko', 'warna_varian' => 'Pink', 'ujung_mata' => '0.5mm Gel'],
            ],
            [
                'key' => 'pulpen-standard-ae7-pink',
                'always' => true,
                'name' => 'Pulpen Standard AE7 Pastel Pink 0.5mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen & Pena Pastel',
                'brand' => 'Standard',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [2500, 3000],
                'stock_range' => [20, 80],
                'image_path' => 'fotocopy_catalog/pulpen-standard-ae7-merah-1pcs.webp',
                'description' => 'Pulpen Standard AE7 bodi pastel pink ringan 0.5mm, harga hemat favorit pelajar dan mahasiswa.',
                'metadata' => ['merk' => 'Standard', 'warna_varian' => 'Pastel Pink', 'ujung_mata' => '0.5mm'],
            ],
            [
                'key' => 'pulpen-gel-ungu-mint-biru',
                'always' => false,
                'name' => 'Pulpen Gel Warna Ungu / Mint / Biru Langit 0.5mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen Warna-Warni',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [3500, 4000],
                'stock_range' => [12, 45],
                'image_path' => 'fotocopy_catalog/pulpen-4-warna-multifungsi.webp',
                'description' => 'Pulpen gel pastel aneka warna ungu, mint green, dan biru langit 0.5mm untuk mind-mapping catatan kuliah.',
                'metadata' => ['warna_varian' => 'Ungu / Mint / Biru Langit', 'ujung_mata' => '0.5mm Gel'],
            ],
            [
                'key' => 'pulpen-snowman-drawing-pen',
                'always' => false,
                'name' => 'Pulpen Snowman Drawing Pen 0.1 / 0.3 / 0.5mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Drawing Pen & Teknik',
                'brand' => 'Snowman',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [9000, 10000, 11000],
                'stock_range' => [10, 35],
                'image_path' => 'fotocopy_catalog/pulpen-snowman-v5-07mm-1pcs.webp',
                'description' => 'Snowman Drawing Pen tinta pigmen hitam tahan air untuk gambar teknik, peta konsep, dan arsitektur.',
                'metadata' => ['merk' => 'Snowman', 'ukuran' => '0.1 / 0.3 / 0.5mm', 'tinta' => 'Pigment Waterproof'],
            ],

            // === KELOMPOK 2: TIPE-X / CORRECTION TAPE & CAIR (CORE) ===
            [
                'key' => 'tipex-kertas-joyko-ct522',
                'always' => true,
                'name' => 'Tipe-X Kertas Joyko CT-522 (Correction Tape 12m)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Tipe-X & Correction Tape',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [7000, 7500, 8500],
                'stock_range' => [12, 48],
                'image_path' => 'fotocopy_catalog/correction-tape-joyko-5mm-12m.webp',
                'description' => 'Tipe-X kertas / correction tape Joyko CT-522 panjang pita 12m x 5mm, langsung kering bersih tanpa tunggu.',
                'metadata' => ['merk' => 'Joyko', 'tipe' => 'Correction Tape 12m', 'sinonim' => 'Tipe-X / Tipex Kertas'],
            ],
            [
                'key' => 'tipex-kertas-kenko-pastel',
                'always' => true,
                'name' => 'Tipe-X Kertas Kenko KE-01 Pastel Pink/Biru',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Tipe-X & Correction Tape',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [8000, 8500, 9500],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/correction-tape-kenko-pastel-pink.webp',
                'description' => 'Correction tape Kenko KE-01 varian casing pastel pink & biru, pita tebal halus dan ergonomis.',
                'metadata' => ['merk' => 'Kenko', 'warna_varian' => 'Pastel Pink / Biru', 'tipe' => 'Correction Tape'],
            ],
            [
                'key' => 'tipex-cair-pentel-fine',
                'always' => false,
                'name' => 'Tipe-X Cair Pentel Fine Point',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Tipe-X & Correction Fluid',
                'brand' => 'Pentel',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [15000, 16500, 18000],
                'stock_range' => [6, 25],
                'image_path' => 'fotocopy_catalog/tipe-x-cair-joyko-cf-s205.webp',
                'description' => 'Correction pen / tipe-x cair Pentel Fine Point ujung logam presisi 4.2ml, cepat kering untuk koreksi detail.',
                'metadata' => ['merk' => 'Pentel', 'tipe' => 'Correction Pen / Fluid'],
            ],
            [
                'key' => 'tipex-cair-kenko-ke823',
                'always' => true,
                'name' => 'Tipe-X Cair Kenko KE-823 (Ekonomis)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Tipe-X & Correction Fluid',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [5000, 5500, 6000],
                'stock_range' => [15, 55],
                'image_path' => 'fotocopy_catalog/tipe-x-cair-kenko-ke-01.webp',
                'description' => 'Tipe-X cair / correction pen Kenko KE-823 harga hemat mahasiswa, ujung besi halus mudah ditekan.',
                'metadata' => ['merk' => 'Kenko', 'tipe' => 'Correction Pen Ekonomis'],
            ],
            [
                'key' => 'refill-correction-tape-joyko',
                'always' => false,
                'name' => 'Refill Correction Tape Joyko 6m',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Tipe-X & Correction Tape',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [4500, 5000],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/correction-tape-joyko-5mm-12m.webp',
                'description' => 'Isi ulang pita correction tape / tipe-x kertas Joyko 6m lebar 5mm, hemat biaya dan ramah lingkungan.',
                'metadata' => ['merk' => 'Joyko', 'tipe' => 'Refill Correction Tape 6m'],
            ],

            // === KELOMPOK 3: STABILO, STICKY NOTES & ALAT TULIS HARIAN ===
            [
                'key' => 'stabilo-joyko-pastel-pink',
                'always' => true,
                'name' => 'Stabilo / Highlighter Joyko Pastel Pink',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stabilo & Highlighter',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [6500, 7000, 7500],
                'stock_range' => [14, 50],
                'image_path' => 'fotocopy_catalog/joyko-highlighter-pastel-hl.webp',
                'description' => 'Highlighter penanda buku warna pastel pink lembut Joyko, tidak tembus di kertas HVS dan bookpaper.',
                'metadata' => ['merk' => 'Joyko', 'warna_varian' => 'Pastel Pink', 'tipe' => 'Highlighter'],
            ],
            [
                'key' => 'stabilo-boss-original',
                'always' => false,
                'name' => 'Highlighter Stabilo Boss Original (Kuning / Pink / Hijau)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stabilo & Highlighter',
                'brand' => 'Stabilo',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [11000, 12000],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/stabilo-boss-original-highlighter.webp',
                'description' => 'Stabilo Boss Original German quality teknologi anti-dry-out, pilihan warna kuning, pink, dan hijau.',
                'metadata' => ['merk' => 'Stabilo', 'warna_varian' => 'Kuning / Pink / Hijau'],
            ],
            [
                'key' => 'sticky-notes-pastel-pink-kuning',
                'always' => true,
                'name' => 'Sticky Notes / Post-It 76x76mm Pastel Pink & Kuning',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Sticky Notes & Pembatas',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pad',
                'prices' => [6000, 7000, 8000],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/sticky-notes-3x3-joyko-pad.webp',
                'description' => 'Sticky notes / post-it ukuran standar 76x76mm isi 100 lembar warna pastel pink & kuning untuk catatan revisi.',
                'metadata' => ['ukuran' => '76 x 76 mm', 'warna_varian' => 'Pastel Pink & Kuning'],
            ],
            [
                'key' => 'pensil-mekanik-joyko-05',
                'always' => false,
                'name' => 'Pensil Mekanik Joyko 0.5mm + Isi Pensil 2B',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pensil & Penghapus',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'set',
                'prices' => [7500, 8500],
                'stock_range' => [10, 35],
                'image_path' => 'fotocopy_catalog/pensil-mekanik-joyko-05mm.webp',
                'description' => 'Paket hemat pensil mekanik 0.5mm berikut 1 tube isi ulang lead 2B anti patah.',
                'metadata' => ['merk' => 'Joyko', 'ukuran' => '0.5mm 2B'],
            ],
            [
                'key' => 'pensil-2b-faber-ujian',
                'always' => false,
                'name' => 'Pensil 2B Faber-Castell (Ujian)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pensil & Penghapus',
                'brand' => 'Faber-Castell',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [5000, 5500],
                'stock_range' => [20, 80],
                'image_path' => 'fotocopy_catalog/pensil-faber-castell-2b-ujian-1pcs.webp',
                'description' => 'Pensil 2B original Faber-Castell terbaca jelas oleh scanner LJK ujian UTS/UAS.',
                'metadata' => ['merk' => 'Faber-Castell', 'kekerasan' => '2B Original'],
            ],
            [
                'key' => 'penghapus-faber-dustfree',
                'always' => false,
                'name' => 'Penghapus Faber-Castell Putih/Hitam',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pensil & Penghapus',
                'brand' => 'Faber-Castell',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [4000, 4500],
                'stock_range' => [20, 70],
                'image_path' => 'fotocopy_catalog/penghapus-faber-castell-dust-free.webp',
                'description' => 'Penghapus karet dust-free bersih tanpa merusak permukaan kertas HVS maupun LJK.',
                'metadata' => ['merk' => 'Faber-Castell', 'warna_varian' => 'Putih / Hitam'],
            ],
            [
                'key' => 'penggaris-besi-30cm',
                'always' => false,
                'name' => 'Penggaris Besi 30cm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Penggaris & Geometri',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [7000, 8000],
                'stock_range' => [10, 35],
                'image_path' => 'fotocopy_catalog/penggaris-besi-stainless-30cm.webp',
                'description' => 'Penggaris stainless steel 30cm presisi tahan lama untuk memotong kertas pakai cutter.',
                'metadata' => ['bahan' => 'Stainless Steel', 'panjang' => '30 cm'],
            ],
            [
                'key' => 'penggaris-mika-butterfly-30cm',
                'always' => false,
                'name' => 'Penggaris Mika Butterfly 30cm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Penggaris & Geometri',
                'brand' => 'Butterfly',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [4000, 4500],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/penggaris-plastik-butterfly-30cm.webp',
                'description' => 'Penggaris plastik bening merk Butterfly 30cm tebal dengan skala sentimeter & inci jelas.',
                'metadata' => ['merk' => 'Butterfly', 'panjang' => '30 cm'],
            ],
            [
                'key' => 'spidol-whiteboard-snowman',
                'always' => false,
                'name' => 'Spidol Whiteboard Snowman Hitam/Biru/Merah',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Spidol & Marker',
                'brand' => 'Snowman',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [8500, 9500],
                'stock_range' => [15, 60],
                'image_path' => 'fotocopy_catalog/spidol-whiteboard-snowman-bg12-1pcs.webp',
                'description' => 'Spidol papan tulis Snowman BG-12 mudah dihapus, tersedia tinta hitam, biru, dan merah.',
                'metadata' => ['merk' => 'Snowman', 'warna_varian' => 'Hitam / Biru / Merah'],
            ],
            [
                'key' => 'spidol-permanen-snowman-g12',
                'always' => false,
                'name' => 'Spidol Permanen Snowman G-12',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Spidol & Marker',
                'brand' => 'Snowman',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [8000, 9000],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/spidol-permanen-snowman-g12.webp',
                'description' => 'Spidol permanen Snowman G-12 tahan air untuk label paket, CD skripsi, kardus, dan plastik.',
                'metadata' => ['merk' => 'Snowman', 'tipe' => 'Permanent Marker'],
            ],
            [
                'key' => 'stapler-mini-hd10-set',
                'always' => false,
                'name' => 'Stapler Mini HD-10 + Isi Staples No. 10',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stapler & Klip',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'set',
                'prices' => [12000, 13500],
                'stock_range' => [8, 30],
                'image_path' => 'fotocopy_catalog/stapler-joyko-hd-10.webp',
                'description' => 'Paket stapler besi HD-10 lengkap dengan 1 kotak isi staples No. 10 untuk bundel tugas kuliah.',
                'metadata' => ['ukuran' => 'HD-10', 'kelengkapan' => 'Stapler + 1 Box Isi No.10'],
            ],
            [
                'key' => 'paper-clip-binder-clip-warna',
                'always' => false,
                'name' => 'Paper Clip & Binder Clip Warna',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stapler & Klip',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'box',
                'prices' => [5000, 6000],
                'stock_range' => [12, 45],
                'image_path' => 'fotocopy_catalog/binder-clip-no107-box-12.webp',
                'description' => 'Paket klip kertas & binder clip aneka warna untuk merapikan berkas revisi skripsi dan makalah.',
                'metadata' => ['isi' => '12 pcs binder clip + paper clip'],
            ],
            [
                'key' => 'gunting-joyko-sedang',
                'always' => false,
                'name' => 'Gunting Kertas Joyko Sedang',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Alat Potong & Perekat',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [9000, 10000],
                'stock_range' => [8, 30],
                'image_path' => 'fotocopy_catalog/gunting-kertas-stainless-joyko-sedang.webp',
                'description' => 'Gunting stainless steel tajam ukuran sedang gagang karet nyaman untuk keperluan tugas & prakarya.',
                'metadata' => ['merk' => 'Joyko', 'bahan' => 'Stainless Steel'],
            ],
            [
                'key' => 'cutter-kenko-a300',
                'always' => false,
                'name' => 'Cutter Kenko A-300',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Alat Potong & Perekat',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [6000, 7000],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/cutter-kecil-joyko-a300.webp',
                'description' => 'Pisau cutter kecil Kenko A-300 pengunci otomatis untuk memotong kertas, foto, dan maket.',
                'metadata' => ['merk' => 'Kenko', 'tipe' => 'A-300 Small Cutter'],
            ],
            [
                'key' => 'lem-stik-uhu-glue-stick',
                'always' => false,
                'name' => 'Lem Stik UHU / Glue Stick',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Alat Potong & Perekat',
                'brand' => 'UHU',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [11000, 12500],
                'stock_range' => [8, 35],
                'image_path' => 'fotocopy_catalog/lem-uhu-all-purpose-cair-tube.webp',
                'description' => 'Lem kertas UHU / glue stick bersih tidak mengkerut di kertas dan mudah dicuci.',
                'metadata' => ['merk' => 'UHU', 'aplikasi' => 'Kertas & Karton'],
            ],
            [
                'key' => 'double-tape-12mm',
                'always' => false,
                'name' => 'Double Tape 12mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Lakban & Perekat',
                'brand' => 'Daimaru',
                'pack_qty' => 1,
                'unit' => 'roll',
                'prices' => [5000, 5500],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/double-tape-12mm-kecil.webp',
                'description' => 'Double tape dua sisi lebar 12mm daya rekat kuat untuk tempel poster, mading, dan sampul.',
                'metadata' => ['lebar' => '12 mm', 'satuan' => 'Roll'],
            ],
            [
                'key' => 'lakban-bening-coklat-48mm',
                'always' => false,
                'name' => 'Lakban Bening / Coklat 48mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Lakban & Perekat',
                'brand' => 'Daimaru',
                'pack_qty' => 1,
                'unit' => 'roll',
                'prices' => [12000, 13000],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/lakban-bening-2-inch-daimaru.webp',
                'description' => 'OPP Tape / lakban bening maupun coklat lebar 48mm (2 inch) kuat untuk pengepakan dus dan arsip.',
                'metadata' => ['lebar' => '48 mm / 2 Inch', 'warna_varian' => 'Bening / Coklat'],
            ],
            [
                'key' => 'map-kancing-f4-warna',
                'always' => true,
                'name' => 'Map Kancing Plastik Transparan F4 (Pink / Biru / Bening)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stopmap & Binder',
                'brand' => 'Bambi',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [3500, 4000, 4500],
                'stock_range' => [20, 75],
                'image_path' => 'fotocopy_catalog/map-kancing-plastik-1-folio.webp',
                'description' => 'Stopmap / map kancing plastik PP tebal ukuran Folio/F4 warna pink, biru, dan bening tahan air.',
                'metadata' => ['ukuran' => 'F4 / Folio', 'warna_varian' => 'Pink / Biru / Bening'],
            ],
            [
                'key' => 'map-zipper-jaring-a4-f4',
                'always' => false,
                'name' => 'Map Zipper Jaring A4/F4',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Stopmap & Binder',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [9000, 10500],
                'stock_range' => [12, 40],
                'image_path' => 'fotocopy_catalog/map-resleting-zipper-bag-f4.webp',
                'description' => 'Zipper bag resleting jaring A4/F4 muat buku teks, alat tulis, dan dokumen praktikum.',
                'metadata' => ['ukuran' => 'A4 / F4', 'penutup' => 'Resleting'],
            ],
            [
                'key' => 'amplop-coklat-tali-eceran',
                'always' => false,
                'name' => 'Amplop Coklat Tali A4/F4 (Eceran)',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Amplop & Pengarsipan',
                'brand' => 'Executive',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [2000, 2500],
                'stock_range' => [25, 100],
                'image_path' => 'fotocopy_catalog/amplop-coklat-tali-a4-1pcs.webp',
                'description' => 'Amplop coklat kraft tali bertutup kancing pengaman berkas lamaran, KRS, dan dokumen skripsi A4/F4.',
                'metadata' => ['ukuran' => 'A4 / F4', 'penjualan' => 'Eceran 1 pcs'],
            ],
            [
                'key' => 'loose-leaf-b5-a5-50lbr',
                'always' => false,
                'name' => 'Loose Leaf B5/A5 50 Lembar',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Kertas & Buku Catatan',
                'brand' => 'Joyko',
                'pack_qty' => 1,
                'unit' => 'pak',
                'prices' => [9500, 10500],
                'stock_range' => [12, 45],
                'image_path' => 'fotocopy_catalog/loose-leaf-b5-bergaris-100-lembar.webp',
                'description' => 'Isi kertas binder loose leaf B5 (26 lubang) / A5 (20 lubang) isi 50 lembar HVS 70gsm putih cerah.',
                'metadata' => ['ukuran' => 'B5 / A5', 'isi' => '50 Lembar'],
            ],
            [
                'key' => 'buku-campus-spiral-a5',
                'always' => false,
                'name' => 'Buku Catatan Campus / Spiral A5',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Kertas & Buku Catatan',
                'brand' => 'SiDU',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [12000, 13000],
                'stock_range' => [10, 40],
                'image_path' => 'fotocopy_catalog/buku-campus-big-boss-boxy-50lbr.webp',
                'description' => 'Buku tulis campus & catatan ring spiral A5 kertas halus cocok untuk kuliah dan seminar.',
                'metadata' => ['ukuran' => 'A5 / Boxy'],
            ],
            [
                'key' => 'materai-10000-asli',
                'always' => true,
                'name' => 'Materai 10.000 Asli',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Dokumen & Legal',
                'brand' => 'PERURI',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [12000, 12500, 13000],
                'stock_range' => [15, 60],
                'image_path' => 'fotocopy_catalog/materai-10000-resmi-original.webp',
                'description' => 'Meterai tempel nominal Rp 10.000 asli Peruri untuk surat pernyataan beasiswa, kuesioner, dan legalisasi.',
                'metadata' => ['nominal' => 'Rp 10.000', 'keaslian' => 'Resmi PERURI'],
            ],
            [
                'key' => 'flashdisk-sandisk-32gb',
                'always' => false,
                'name' => 'Flashdisk Sandisk 32GB USB 3.0',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Media Penyimpanan',
                'brand' => 'SanDisk',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [65000, 68000, 72000],
                'stock_range' => [4, 15],
                'image_path' => 'fotocopy_catalog/flashdisk-sandisk-cruzer-blade-32gb.webp',
                'description' => 'USB Flashdisk SanDisk 32GB kecepatan tinggi untuk back-up file skripsi, presentasi, dan olah data.',
                'metadata' => ['merk' => 'SanDisk', 'kapasitas' => '32GB'],
            ],
            [
                'key' => 'pulpen-kenko-easy-gel-pink-biru',
                'always' => false,
                'name' => 'Pulpen Kenko Easy Gel Pink & Biru 0.5mm',
                'category' => 'ATK',
                'item_type' => 'barang',
                'sub_category' => 'Pulpen & Pena Gel Pink',
                'brand' => 'Kenko',
                'pack_qty' => 1,
                'unit' => 'pcs',
                'prices' => [4000, 4500],
                'stock_range' => [15, 50],
                'image_path' => 'fotocopy_catalog/pulpen-gel-pink-pastel-05mm.webp',
                'description' => 'Pulpen Kenko Easy Gel 0.5mm varian tinta pink & biru pastel dengan pegangan nyaman.',
                'metadata' => ['merk' => 'Kenko', 'warna_varian' => 'Pink & Biru', 'ujung_mata' => '0.5mm Gel'],
            ],

            // === KELOMPOK 4: JASA PERCETAKAN & FINISHING POPULER ===
            [
                'key' => 'cetak-stiker-a3-vinyl-chromo',
                'always' => false,
                'name' => 'Cetak Stiker A3+ Vinyl / Chromo (Cut / Non-Cut)',
                'category' => 'Print',
                'item_type' => 'jasa',
                'sub_category' => 'Cetak Stiker & Merchandise',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'lembar A3+',
                'prices' => [12000, 15000, 18000],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/print-stiker-vinyl-a4.webp',
                'description' => 'Print stiker bahan Vinyl tahan air atau Chromo mengkilap ukuran A3+, opsi dengan kiss-cut sesuai pola.',
                'metadata' => ['ukuran' => 'A3+ (32 x 48 cm)', 'bahan' => 'Vinyl / Chromo'],
            ],
            [
                'key' => 'cetak-sertifikat-artcarton-linen',
                'always' => true,
                'name' => 'Cetak Sertifikat Art Carton 260gsm / Linen A4',
                'category' => 'Print',
                'item_type' => 'jasa',
                'sub_category' => 'Cetak Sertifikat & Piagam',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'lembar',
                'prices' => [5000, 6000, 7000],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/print-sertifikat-concorde-linen-a4.webp',
                'description' => 'Cetak piagam kepanitiaan dan sertifikat seminar memakai kertas bertekstur Linen atau Art Carton 260gsm.',
                'metadata' => ['ukuran' => 'A4', 'kertas' => 'Art Carton 260gsm / Concorde Linen'],
            ],
            [
                'key' => 'cetak-poster-a3-artpaper',
                'always' => false,
                'name' => 'Cetak Poster Warna A3 Art Paper 150gsm',
                'category' => 'Print',
                'item_type' => 'jasa',
                'sub_category' => 'Cetak Poster & Visual',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'lembar',
                'prices' => [8000, 9000, 10000],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/print-poster-a2.webp',
                'description' => 'Cetak poster seminar ilmiah, kegiatan UKM, dan presentasi tugas ukuran A3 full color pada Art Paper 150gsm.',
                'metadata' => ['ukuran' => 'A3', 'kertas' => 'Art Paper 150gsm Glossy'],
            ],
            [
                'key' => 'cetak-pas-foto-paket',
                'always' => false,
                'name' => 'Cetak Pas Foto 2x3 / 3x4 / 4x6 (Paket 4-8 Lembar)',
                'category' => 'Digital',
                'item_type' => 'jasa',
                'sub_category' => 'Cetak Foto & Ijazah',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'paket',
                'prices' => [10000, 12000, 15000],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/cetak-pas-foto-3x4-paket-6.webp',
                'description' => 'Cetak pas foto resmi ukuran 2x3, 3x4, atau 4x6 kertas silky doff/glossy termasuk potong rapi siap pakai.',
                'metadata' => ['ukuran' => '2x3 / 3x4 / 4x6 cm', 'isi' => 'Paket 4-8 Lembar'],
            ],
            [
                'key' => 'jilid-spiral-kawat-plastik-mika',
                'always' => true,
                'name' => 'Jilid Spiral Kawat / Plastik + Cover Mika',
                'category' => 'Jilid',
                'item_type' => 'jasa',
                'sub_category' => 'Jilid Spiral & Modul',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'buku',
                'prices' => [12000, 15000, 18000],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/jilid-spiral-kawat-ring.webp',
                'description' => 'Penjilidan ring kawat atau plastik dilengkapi sampul mika bening depan dan kertas buffalo belakang.',
                'metadata' => ['kapasitas' => 'Hingga 200 lembar', 'cover' => 'Mika + Buffalo'],
            ],
            [
                'key' => 'scan-warna-pdf-gabung',
                'always' => true,
                'name' => 'Scan Dokumen Warna ke PDF + Gabung File',
                'category' => 'Digital',
                'item_type' => 'jasa',
                'sub_category' => 'Scan & Digitalisasi',
                'brand' => null,
                'pack_qty' => 1,
                'unit' => 'halaman',
                'prices' => [2000, 2500],
                'stock_range' => null,
                'image_path' => 'fotocopy_catalog/scan-dokumen-pdf-jpg-per-lembar.webp',
                'description' => 'Pemindaian dokumen berwarna 300 DPI otomatis menjadi 1 file PDF tergabung siap unggah portal kampus.',
                'metadata' => ['resolusi' => '300 DPI Full Color', 'output' => 'Merged PDF'],
            ],
        ];
    }

    /**
     * Untuk 20 toko non-prioritas, pastikan item core (`always=true`, mencakup Pulpen Pink,
     * Tipe-X Kertas & Cair, Stabilo Pink, Sticky Notes, Map Kancing, Materai, & Jasa utama)
     * selalu tersedia ditambah 10-12 item sekunder deterministik sehingga hasil search bervariasi.
     *
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    protected function selectStoreTemplates(array $templates, string $slug): array
    {
        $selected = [];
        $optional = [];

        foreach ($templates as $tpl) {
            if (!empty($tpl['always'])) {
                $selected[] = $tpl;
            } else {
                $optional[] = $tpl;
            }
        }

        usort($optional, function (array $a, array $b) use ($slug): int {
            $ha = $this->h($slug . '|' . $a['key']);
            $hb = $this->h($slug . '|' . $b['key']);

            return $ha === $hb ? strcmp($a['key'], $b['key']) : ($ha <=> $hb);
        });

        $extraTake = 10 + ($this->h($slug . '|count') % 5); // 10..14 ekstra item
        foreach (array_slice($optional, 0, $extraTake) as $opt) {
            $selected[] = $opt;
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $tpl
     * @return array<string, mixed>
     */
    protected function buildPayloadForStore(array $tpl, string $slug): array
    {
        $prices = $tpl['prices'];
        $price = (int) $prices[$this->h($slug . '|' . $tpl['key'] . '|p') % count($prices)];

        $stock = null;
        if (($tpl['item_type'] ?? 'barang') === 'barang' && is_array($tpl['stock_range'] ?? null)) {
            $minS = (int) $tpl['stock_range'][0];
            $maxS = (int) $tpl['stock_range'][1];
            $stock = $minS + ($this->h($slug . '|' . $tpl['key'] . '|s') % max(1, $maxS - $minS + 1));
        }

        $imagePath = ltrim((string) $tpl['image_path'], '/');
        if (!is_file(public_path('storage/' . $imagePath))) {
            $imagePath = 'fotocopy_catalog/print-hitam-putih-a4-70gsm.webp';
        }

        return [
            'name' => (string) $tpl['name'],
            'category' => (string) $tpl['category'],
            'item_type' => (string) $tpl['item_type'],
            'sub_category' => $tpl['sub_category'] ?? null,
            'brand' => $tpl['brand'] ?? null,
            'pack_qty' => (int) ($tpl['pack_qty'] ?? 1),
            'unit' => (string) $tpl['unit'],
            'price' => $price,
            'description' => (string) $tpl['description'],
            'image_path' => $imagePath,
            'metadata' => json_encode($tpl['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stock' => $stock,
            'is_available' => 1,
        ];
    }

    /**
     * Upsert idempoten tanpa menghapus katalog lama toko.
     *
     * @param  array<string, array<string, mixed>>  $desiredRows
     * @return array{0: int, 1: int}
     */
    protected function upsertCatalogRows(string $table, string $fkCol, int $fkId, array $desiredRows, string $now): array
    {
        $existing = DB::table($table)->where($fkCol, $fkId)->get()->keyBy('name');
        $inserted = 0;
        $updated = 0;
        $toInsert = [];

        foreach ($desiredRows as $name => $payload) {
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

        return [$inserted, $updated];
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
            || (int) $cur->is_available !== (int) $desired['is_available'];
    }

    protected function h(string $input): int
    {
        return (int) sprintf('%u', crc32($input));
    }
}
