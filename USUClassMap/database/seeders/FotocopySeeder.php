<?php

namespace Database\Seeders;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FotocopySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Akun Admin Fotokopi (Bisa login dengan 'adminfotokopi' atau 'adminfotokopi@usu.ac.id')
        $adminFotokopi = User::updateOrCreate(
            ['email' => 'adminfotokopi@usu.ac.id'],
            [
                'name' => 'Admin Fotokopi USU',
                'nim' => 'adminfotokopi',
                'semester' => 'Staff',
                'password' => Hash::make('adminusu123'),
                'role' => 'admin_fotokopi',
            ]
        );

        // 2. Seed Akun Cashier Fotokopi (Bisa login dengan 'cashierfotokopi' atau 'cashierfotokopi@usu.ac.id')
        $cashierFotokopi = User::updateOrCreate(
            ['email' => 'cashierfotokopi@usu.ac.id'],
            [
                'name' => 'Kasir Berkah Abadi',
                'nim' => 'cashierfotokopi',
                'semester' => 'Staff',
                'password' => Hash::make('adminusu123'),
                'role' => 'cashier_fotokopi',
            ]
        );

        // 3. Seed 6 Mitra Toko Fotokopi Kampus USU (Sesuai Referensi Tampilan)
        $storesData = [
            [
                'id' => 5,
                'user_id' => $cashierFotokopi->id,
                'name' => 'Berkah Abadi Copy & ATK Perpustakaan',
                'slug' => 'berkah-abadi-copy-atk-perpustakaan',
                'location_name' => 'Pelataran Parkir Perpustakaan Pusat USU',
                'latitude' => 3.565250,
                'longitude' => 98.654850,
                'opening_hours' => '07:30 - 20:30',
                'operating_days' => 'Senin - Sabtu',
                'whatsapp' => '+6282277809900',
                'instagram' => '@berkahabadi_usu',
                'facebook' => 'berkahabadi.copy',
                'description' => 'Paling strategis di dekat Perpustakaan Pusat USU. Melayani fotocopy referensi buku perpus, cetak online via WA, dan perlengkapan ujian.',
                'rating' => 4.7,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    [
                        'name' => 'Print Tugas A4 B/W',
                        'category' => 'Print',
                        'price' => 200,
                        'unit' => 'lembar',
                        'description' => 'Langsung kirim file via WA',
                    ],
                    [
                        'name' => 'Print Warna A4',
                        'category' => 'Print',
                        'price' => 800,
                        'unit' => 'lembar',
                        'description' => 'Kualitas jernih',
                    ],
                    [
                        'name' => 'Fotocopy A4/F4',
                        'category' => 'Fotocopy',
                        'price' => 175,
                        'unit' => 'lembar',
                        'description' => 'Pelayanan ramah',
                    ],
                    [
                        'name' => 'Jilid Spiral Kawat A4',
                        'category' => 'Jilid',
                        'price' => 10000,
                        'unit' => 'buku',
                        'description' => 'Cover mika bening tebal',
                    ],
                    [
                        'name' => 'Pulpen Gel Standard / Faster',
                        'category' => 'ATK',
                        'price' => 3500,
                        'unit' => 'pcs',
                        'description' => 'Tinta hitam/biru',
                    ],
                ]
            ],
            [
                'id' => 1,
                'user_id' => null,
                'name' => 'Copy Corner Fakultas Hukum',
                'slug' => 'copy-corner-fakultas-hukum',
                'location_name' => 'Lobi Sayap Barat Fakultas Hukum (FH) USU',
                'latitude' => 3.564100,
                'longitude' => 98.653900,
                'opening_hours' => '08:00 - 18:00',
                'operating_days' => 'Senin - Jumat',
                'whatsapp' => '+6281234567891',
                'instagram' => '@copycorner_fh',
                'facebook' => 'copycorner.fh',
                'description' => 'Tempat fotocopy buku teks hukum, KUHP, berkas peradilan semu, perbanyakan berkas skripsi, serta meterai resmi.',
                'rating' => 4.6,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    ['name' => 'Print Hitam Putih A4', 'category' => 'Print', 'price' => 200, 'unit' => 'lembar', 'description' => 'Cepat & praktis'],
                    ['name' => 'Print Warna A4', 'category' => 'Print', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Tinta laser tajam'],
                    ['name' => 'Fotocopy Buku Hukum A4/F4', 'category' => 'Fotocopy', 'price' => 175, 'unit' => 'lembar', 'description' => 'Buku tebal rapi'],
                    ['name' => 'Jilid Lakban Biasa', 'category' => 'Jilid', 'price' => 5000, 'unit' => 'buku', 'description' => 'Lakban hitam standar'],
                ]
            ],
            [
                'id' => 2,
                'user_id' => null,
                'name' => 'Digital Print Center FK & FIB',
                'slug' => 'digital-print-center-fk-fib',
                'location_name' => 'Belakang Gedung Fakultas Kedokteran (FK) USU',
                'latitude' => 3.563500,
                'longitude' => 98.656200,
                'opening_hours' => '07:00 - 22:00',
                'operating_days' => 'Senin - Sabtu',
                'whatsapp' => '+6281298765432',
                'instagram' => '@digitalprint_fkfib',
                'facebook' => 'digitalprint.fk',
                'description' => 'Spesialis cetak poster penelitian ilmiah, atlas kedokteran, cetak diktat tebal, banner seminar, dan cetak kilat warna tajam.',
                'rating' => 4.9,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    ['name' => 'Print Laser Hitam Putih A4', 'category' => 'Print', 'price' => 250, 'unit' => 'lembar', 'description' => 'Resolusi tinggi'],
                    ['name' => 'Print Warna Laser High Res A4', 'category' => 'Print', 'price' => 1500, 'unit' => 'lembar', 'description' => 'Glossy / Matte'],
                    ['name' => 'Cetak Poster Ilmiah / Banner A1', 'category' => 'Print', 'price' => 35000, 'unit' => 'lembar', 'description' => 'Bahan Albatros / Luster'],
                    ['name' => 'Jilid Hardcover Skripsi', 'category' => 'Jilid', 'price' => 35000, 'unit' => 'buku', 'description' => 'Tinta emas embos'],
                ]
            ],
            [
                'id' => 3,
                'user_id' => null,
                'name' => 'Fotocopy & Print Jaya FISIP',
                'slug' => 'fotocopy-print-jaya-fisip',
                'location_name' => 'Samping Gedung FISIP USU (Dekat Kantin)',
                'latitude' => 3.566800,
                'longitude' => 98.652800,
                'opening_hours' => '08:00 - 21:30',
                'operating_days' => 'Senin - Minggu',
                'whatsapp' => '+6285211223344',
                'instagram' => '@jayafisip_copy',
                'facebook' => 'jayaprint.fisip',
                'description' => 'Pusat fotocopy dan print mahasiswa FISIP & FIB. Menyediakan cetak cepat, jilid softcover/hardcover, dan cetak foto kilat.',
                'rating' => 4.8,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    ['name' => 'Print Hitam Putih A4', 'category' => 'Print', 'price' => 200, 'unit' => 'lembar', 'description' => 'Kertas 75gr'],
                    ['name' => 'Print Warna A4', 'category' => 'Print', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Full color'],
                    ['name' => 'Fotocopy A4/F4', 'category' => 'Fotocopy', 'price' => 180, 'unit' => 'lembar', 'description' => 'Mesin Canon high speed'],
                    ['name' => 'Jilid Softcover', 'category' => 'Jilid', 'price' => 15000, 'unit' => 'buku', 'description' => 'Laminating doff'],
                ]
            ],
            [
                'id' => 4,
                'user_id' => null,
                'name' => 'Fotocopy Pak Budi FT',
                'slug' => 'fotocopy-pak-budi-ft',
                'location_name' => 'Depan Gedung Fakultas Teknik (FT) USU',
                'latitude' => 3.561900,
                'longitude' => 98.655100,
                'opening_hours' => '07:30 - 21:00',
                'operating_days' => 'Senin - Sabtu',
                'whatsapp' => '+6281355667788',
                'instagram' => '@pakbudi_ft',
                'facebook' => 'pakbudi.ft',
                'description' => 'Melayani fotocopy kilat, print tugas/makalah, print skripsi, jilid lakban & spiral, dan penjualan ATK lengkap mahasiswa teknik.',
                'rating' => 4.9,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    ['name' => 'Print Tugas/Makalah B/W', 'category' => 'Print', 'price' => 200, 'unit' => 'lembar', 'description' => 'Format PDF/Docx'],
                    ['name' => 'Print Gambar Teknik A3', 'category' => 'Print', 'price' => 3000, 'unit' => 'lembar', 'description' => 'Plotter AutoCAD'],
                    ['name' => 'Jilid Spiral Kawat', 'category' => 'Jilid', 'price' => 12000, 'unit' => 'buku', 'description' => 'Mika bening + karton'],
                    ['name' => 'Kertas Kalkir A3', 'category' => 'ATK', 'price' => 5000, 'unit' => 'lembar', 'description' => 'Untuk gambar teknik'],
                ]
            ],
            [
                'id' => 6,
                'user_id' => null,
                'name' => 'Rizky Multi Print USU (Pintu 1)',
                'slug' => 'rizky-multi-print-usu-pintu-1',
                'location_name' => 'Pintu 1 Kampus USU, Jl. Dr. Mansyur No. 28 Medan',
                'latitude' => 3.568200,
                'longitude' => 98.658300,
                'opening_hours' => '07:00 - 23:00',
                'operating_days' => 'Senin - Minggu',
                'whatsapp' => '+6281988990011',
                'instagram' => '@rizkymultiprint',
                'facebook' => 'rizkyprint.pintu1',
                'description' => 'Buka sampai malam! Melayani print 24 jam dengan konfirmasi WA, pembuatan plakat akrilik, cetak sertifikat, dan print skripsi kilat.',
                'rating' => 4.9,
                'is_open' => true,
                'starting_price' => 200,
                'services' => [
                    ['name' => 'Print Skripsi Kilat A4', 'category' => 'Print', 'price' => 250, 'unit' => 'lembar', 'description' => 'Kertas 80gr PaperOne'],
                    ['name' => 'Plakat Akrilik Wisuda', 'category' => 'ATK', 'price' => 45000, 'unit' => 'pcs', 'description' => 'Custom nama & logo USU'],
                    ['name' => 'Fotocopy Bolak-Balik', 'category' => 'Fotocopy', 'price' => 190, 'unit' => 'lembar', 'description' => 'Hemat & rapi'],
                ]
            ],
        ];

        foreach ($storesData as $storeInfo) {
            $services = $storeInfo['services'];
            unset($storeInfo['services']);

            $store = FotocopyStore::updateOrCreate(['id' => $storeInfo['id']], $storeInfo);

            foreach ($services as $srv) {
                FotocopyService::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $srv['name']
                    ],
                    $srv
                );
            }
        }

        // 4. Seed Beberapa Pesanan Masuk (Sample Orders) untuk Berkah Abadi
        $berkahStore = FotocopyStore::find(5);
        if ($berkahStore) {
            $order1 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0001'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Budi Santoso (NIM 211402011)',
                    'customer_phone' => '081234567890',
                    'notes' => 'Tolong rangkap 2, jilid lakban hitam, kertas 80gr. File terlampir langsung.',
                    'file_name' => 'Makalah_Studi_Kasus_MKWU_Kelompok4.pdf',
                    'file_size' => '2.4 MB',
                    'file_type' => 'pdf',
                    'file_path' => 'fotocopy_docs/sample_makalah_mkwu.pdf',
                    'payment_method' => 'QRIS',
                    'payment_status' => 'paid',
                    'order_status' => 'Menunggu',
                    'total_price' => 35000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Print Tugas A4 B/W'],
                [
                    'category' => 'Print',
                    'price' => 200,
                    'quantity' => 50,
                    'subtotal' => 10000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Print Warna A4'],
                [
                    'category' => 'Print',
                    'price' => 800,
                    'quantity' => 20,
                    'subtotal' => 16000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order1->id, 'service_name' => 'Jilid Spiral Kawat A4'],
                [
                    'category' => 'Jilid',
                    'price' => 10000,
                    'quantity' => 1,
                    'subtotal' => 10000,
                ]
            );

            $order2 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0002'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Siti Nurhaliza (NIM 221401045)',
                    'customer_phone' => '082199887766',
                    'notes' => 'Fotocopy modul kuliah halaman 1-40, staples sudut kiri atas saja.',
                    'file_name' => 'Modul_Praktikum_Jaringan_Komputer.pdf',
                    'file_size' => '1.8 MB',
                    'file_type' => 'pdf',
                    'file_path' => 'fotocopy_docs/sample_modul_jaringan.pdf',
                    'payment_method' => 'Tunai',
                    'payment_status' => 'pending',
                    'order_status' => 'Diproses',
                    'total_price' => 7000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order2->id, 'service_name' => 'Fotocopy A4/F4'],
                [
                    'category' => 'Fotocopy',
                    'price' => 175,
                    'quantity' => 40,
                    'subtotal' => 7000,
                ]
            );

            $order3 = FotocopyOrder::updateOrCreate(
                ['order_code' => 'ORD-20261113-0003'],
                [
                    'store_id' => $berkahStore->id,
                    'customer_name' => 'Rizky Pratama (NIM 201402099)',
                    'customer_phone' => '085311224455',
                    'notes' => 'Ambil jam 14:00 WIB, pulpen tinta hitam 2 pcs.',
                    'payment_method' => 'Transfer',
                    'payment_status' => 'paid',
                    'order_status' => 'Selesai',
                    'total_price' => 27000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order3->id, 'service_name' => 'Print Warna A4'],
                [
                    'category' => 'Print',
                    'price' => 800,
                    'quantity' => 25,
                    'subtotal' => 20000,
                ]
            );

            FotocopyOrderItem::updateOrCreate(
                ['order_id' => $order3->id, 'service_name' => 'Pulpen Gel Standard / Faster'],
                [
                    'category' => 'ATK',
                    'price' => 3500,
                    'quantity' => 2,
                    'subtotal' => 7000,
                ]
            );
        }
    }
}
