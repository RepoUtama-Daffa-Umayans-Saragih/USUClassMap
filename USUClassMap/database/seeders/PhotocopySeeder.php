<?php

namespace Database\Seeders;

use App\Models\PhotocopyPlace;
use App\Models\PhotocopyItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PhotocopySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $places = [
            [
                'name' => 'Fotocopy Pak Budi FT',
                'description' => 'Melayani fotocopy kilat, print tugas/makalah, print skripsi, jilid lakban & spiral, dan penjualan ATK lengkap mahasiswa teknik.',
                'location_name' => 'Depan Gedung Fakultas Teknik (FT) USU',
                'latitude' => 3.563812,
                'longitude' => 98.654210,
                'google_maps_url' => 'https://maps.google.com/?q=3.563812,98.654210',
                'open_time' => '07:30',
                'close_time' => '21:00',
                'operating_days' => 'Senin - Sabtu',
                'phone' => '081260123456',
                'whatsapp' => '6281260123456',
                'instagram' => 'fotocopy_pakbudi_ft',
                'facebook' => 'fotocopy.pakbudi.usu',
                'rating' => 4.9,
                'items' => [
                    ['name' => 'Print Hitam Putih A4 (Teks)', 'category' => 'Print', 'price' => 250, 'unit' => 'lembar', 'description' => 'Kertas HVS 75gr, hasil tajam'],
                    ['name' => 'Print Warna A4 (Grafik/Gambar)', 'category' => 'Print', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Tinta inkjet presisi tinggi'],
                    ['name' => 'Print Full Warna A4 (Foto/Poster)', 'category' => 'Print', 'price' => 2500, 'unit' => 'lembar', 'description' => 'Full coverage warna jernih'],
                    ['name' => 'Fotocopy Hitam Putih A4/F4', 'category' => 'Fotocopy', 'price' => 200, 'unit' => 'lembar', 'description' => '1 sisi atau bolak-balik'],
                    ['name' => 'Jilid Lakban Biasa + Mika', 'category' => 'Jilid', 'price' => 3500, 'unit' => 'buku', 'description' => 'Mika bening + lakban hitam'],
                    ['name' => 'Jilid Spiral Kawat', 'category' => 'Jilid', 'price' => 12000, 'unit' => 'buku', 'description' => 'Rapi dan kuat untuk laporan/proposal'],
                    ['name' => 'Jilid Hard Cover Skripsi (Embos Emas)', 'category' => 'Jilid', 'price' => 35000, 'unit' => 'buku', 'description' => 'Standar format skripsi USU'],
                    ['name' => 'Laminating Panas A4', 'category' => 'Finishing', 'price' => 4000, 'unit' => 'lembar', 'description' => 'Plastik laminasi tebal anti air'],
                    ['name' => 'Scan Dokumen ke PDF', 'category' => 'Finishing', 'price' => 500, 'unit' => 'halaman', 'description' => 'Resolusi 300 DPI, kirim via WA/Email'],
                    ['name' => 'Kertas HVS A4 1 Rim (75gr)', 'category' => 'ATK', 'price' => 52000, 'unit' => 'rim', 'description' => 'Isi 500 lembar paperone/natural'],
                ]
            ],
            [
                'name' => 'Fotocopy & Print Jaya FISIP',
                'description' => 'Pusat fotocopy dan print mahasiswa FISIP & FIB. Menyediakan cetak cepat, jilid softcover/hardcover, dan cetak foto kilat.',
                'location_name' => 'Samping Gedung FISIP USU (Dekat Kantin)',
                'latitude' => 3.565120,
                'longitude' => 98.656340,
                'google_maps_url' => 'https://maps.google.com/?q=3.565120,98.656340',
                'open_time' => '08:00',
                'close_time' => '21:30',
                'operating_days' => 'Senin - Minggu',
                'phone' => '082165432109',
                'whatsapp' => '6282165432109',
                'instagram' => 'jayaprint_fisip_usu',
                'facebook' => 'jaya.printing.usu',
                'rating' => 4.8,
                'items' => [
                    ['name' => 'Print Hitam Putih A4', 'category' => 'Print', 'price' => 200, 'unit' => 'lembar', 'description' => 'Kecepatan tinggi, anti luntur'],
                    ['name' => 'Print Warna Dokumen A4', 'category' => 'Print', 'price' => 800, 'unit' => 'lembar', 'description' => 'Cocok untuk tugas makalah'],
                    ['name' => 'Fotocopy A4/F4', 'category' => 'Fotocopy', 'price' => 150, 'unit' => 'lembar', 'description' => 'Harga mahasiswa termurah'],
                    ['name' => 'Jilid Mika Lakban', 'category' => 'Jilid', 'price' => 3000, 'unit' => 'buku', 'description' => 'Pilihan cover berbagai warna'],
                    ['name' => 'Jilid Spiral Plastik', 'category' => 'Jilid', 'price' => 8000, 'unit' => 'buku', 'description' => 'Praktis dan awet'],
                    ['name' => 'Cetak Pas Foto 3x4 / 4x6 (1 Paket)', 'category' => 'Finishing', 'price' => 10000, 'unit' => 'paket', 'description' => 'Kertas foto glossy berkualitas'],
                    ['name' => 'Map Business File / Snelhechter', 'category' => 'ATK', 'price' => 4000, 'unit' => 'pcs', 'description' => 'Untuk berkas ujian dan tugas'],
                ]
            ],
            [
                'name' => 'Digital Print Center FK & FIB',
                'description' => 'Spesialis cetak poster penelitian ilmiah, atlas kedokteran, cetak diktat tebal, banner seminar, dan cetak kilat warna tajam.',
                'location_name' => 'Belakang Gedung Fakultas Kedokteran (FK) USU',
                'latitude' => 3.562145,
                'longitude' => 98.653110,
                'google_maps_url' => 'https://maps.google.com/?q=3.562145,98.653110',
                'open_time' => '07:00',
                'close_time' => '22:00',
                'operating_days' => 'Senin - Sabtu',
                'phone' => '085270112233',
                'whatsapp' => '6285270112233',
                'instagram' => 'digitalprint_medik_usu',
                'facebook' => 'digitalprint.usu',
                'rating' => 4.9,
                'items' => [
                    ['name' => 'Print Laser Hitam Putih A4', 'category' => 'Print', 'price' => 300, 'unit' => 'lembar', 'description' => 'Laserjet anti basah & anti luntur'],
                    ['name' => 'Print Warna Laser High Res A4', 'category' => 'Print', 'price' => 1500, 'unit' => 'lembar', 'description' => 'Cetak bagan anatomi & mikroskop'],
                    ['name' => 'Cetak Poster Ilmiah / Banner A1', 'category' => 'Print', 'price' => 65000, 'unit' => 'lembar', 'description' => 'Bahan Albatros doff/glossy'],
                    ['name' => 'Fotocopy Diktat & Jurnal Medis', 'category' => 'Fotocopy', 'price' => 200, 'unit' => 'lembar', 'description' => 'Diskon order > 100 lembar'],
                    ['name' => 'Jilid Hard Cover Skripsi / Tesis', 'category' => 'Jilid', 'price' => 40000, 'unit' => 'buku', 'description' => 'Tinta emas logo universitas timbul'],
                    ['name' => 'Laminating ID Card / A4', 'category' => 'Finishing', 'price' => 3500, 'unit' => 'lembar', 'description' => 'Presisi tanpa gelembung'],
                ]
            ],
            [
                'name' => 'Copy Corner Fakultas Hukum',
                'description' => 'Tempat fotocopy buku teks hukum, KUHP, berkas peradilan semu, perbanyakan berkas skripsi, serta meterai resmi.',
                'location_name' => 'Lobi Sayap Barat Fakultas Hukum (FH) USU',
                'latitude' => 3.566210,
                'longitude' => 98.655890,
                'google_maps_url' => 'https://maps.google.com/?q=3.566210,98.655890',
                'open_time' => '08:00',
                'close_time' => '18:00',
                'operating_days' => 'Senin - Jumat',
                'phone' => '081398765432',
                'whatsapp' => '6281398765432',
                'instagram' => 'copycorner_fhusu',
                'facebook' => 'copycorner.fhusu',
                'rating' => 4.6,
                'items' => [
                    ['name' => 'Print Hitam Putih A4', 'category' => 'Print', 'price' => 250, 'unit' => 'lembar', 'description' => 'Kertas 75gr standar'],
                    ['name' => 'Print Warna A4', 'category' => 'Print', 'price' => 1000, 'unit' => 'lembar', 'description' => 'Cetak draft tugas'],
                    ['name' => 'Fotocopy Buku Hukum A4/F4', 'category' => 'Fotocopy', 'price' => 180, 'unit' => 'lembar', 'description' => 'Hasil bersih tanpa garis hitam'],
                    ['name' => 'Jilid Lakban Biasa', 'category' => 'Jilid', 'price' => 3000, 'unit' => 'buku', 'description' => 'Proses cepat 5 menit'],
                    ['name' => 'Materai Tempel 10.000 Resmi', 'category' => 'ATK', 'price' => 11500, 'unit' => 'lembar', 'description' => 'Asli dari kantor pos'],
                ]
            ],
            [
                'name' => 'Berkah Abadi Copy & ATK Perpustakaan',
                'description' => 'Paling strategis di dekat Perpustakaan Pusat USU. Melayani fotocopy referensi buku perpus, cetak online via WA, dan perlengkapan ujian.',
                'location_name' => 'Pelataran Parkir Perpustakaan Pusat USU',
                'latitude' => 3.564450,
                'longitude' => 98.657120,
                'google_maps_url' => 'https://maps.google.com/?q=3.564450,98.657120',
                'open_time' => '07:30',
                'close_time' => '20:30',
                'operating_days' => 'Senin - Sabtu',
                'phone' => '082277889900',
                'whatsapp' => '6282277889900',
                'instagram' => 'berkahabadi_usu',
                'facebook' => 'berkahabadi.copy',
                'rating' => 4.7,
                'items' => [
                    ['name' => 'Print Tugas A4 B/W', 'category' => 'Print', 'price' => 200, 'unit' => 'lembar', 'description' => 'Langsung kirim file via WA'],
                    ['name' => 'Print Warna A4', 'category' => 'Print', 'price' => 800, 'unit' => 'lembar', 'description' => 'Kualitas jernih'],
                    ['name' => 'Fotocopy A4/F4', 'category' => 'Fotocopy', 'price' => 175, 'unit' => 'lembar', 'description' => 'Pelayanan ramah'],
                    ['name' => 'Jilid Spiral Kawat A4', 'category' => 'Jilid', 'price' => 10000, 'unit' => 'buku', 'description' => 'Cover mika bening tebal'],
                    ['name' => 'Pulpen Gel Standard / Faster', 'category' => 'ATK', 'price' => 3500, 'unit' => 'pcs', 'description' => 'Tinta hitam/biru'],
                ]
            ],
            [
                'name' => 'Rizky Multi Print USU (Pintu 1)',
                'description' => 'Buka sampai malam! Melayani print 24 jam dengan konfirmasi WA, pembuatan plakat akrilik, cetak sertifikat, dan print skripsi kilat.',
                'location_name' => 'Pintu 1 Kampus USU, Jl. Dr. Mansyur No. 28 Medan',
                'latitude' => 3.567890,
                'longitude' => 98.652450,
                'google_maps_url' => 'https://maps.google.com/?q=3.567890,98.652450',
                'open_time' => '07:00',
                'close_time' => '23:00',
                'operating_days' => 'Senin - Minggu',
                'phone' => '081299887766',
                'whatsapp' => '6281299887766',
                'instagram' => 'rizkymultiprint_medan',
                'facebook' => 'rizkymultiprint.usu',
                'rating' => 4.9,
                'items' => [
                    ['name' => 'Print Skripsi Kilat (Siap 1 Jam)', 'category' => 'Print', 'price' => 300, 'unit' => 'lembar', 'description' => 'Mesin industri berkecepatan tinggi'],
                    ['name' => 'Print Warna Full Color Laser', 'category' => 'Print', 'price' => 1200, 'unit' => 'lembar', 'description' => 'Kertas art paper / HVS 80gr'],
                    ['name' => 'Fotocopy Massal (>500 Lembar)', 'category' => 'Fotocopy', 'price' => 150, 'unit' => 'lembar', 'description' => 'Harga grosir khusus mahasiswa'],
                    ['name' => 'Jilid Hard Cover Skripsi Full Emas', 'category' => 'Jilid', 'price' => 35000, 'unit' => 'buku', 'description' => 'Selesai 1 hari kerja'],
                    ['name' => 'Cetak Sertifikat Kertas Linen/Concorde', 'category' => 'Finishing', 'price' => 3000, 'unit' => 'lembar', 'description' => 'Tekstur mewah untuk sertifikat kegiatan'],
                    ['name' => 'Stiker Vinyl Print & Cut A3+', 'category' => 'Finishing', 'price' => 15000, 'unit' => 'lembar', 'description' => 'Anti air untuk stiker organisasi'],
                ]
            ],
        ];

        foreach ($places as $placeData) {
            $items = $placeData['items'];
            unset($placeData['items']);

            $placeData['slug'] = Str::slug($placeData['name']);
            $place = PhotocopyPlace::updateOrCreate(
                ['slug' => $placeData['slug']],
                $placeData
            );

            // Seed items
            foreach ($items as $itemData) {
                PhotocopyItem::updateOrCreate(
                    [
                        'photocopy_place_id' => $place->id,
                        'name' => $itemData['name'],
                    ],
                    $itemData
                );
            }
        }
    }
}

