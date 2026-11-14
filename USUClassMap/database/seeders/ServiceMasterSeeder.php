<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Print Dokumen Full Color',
                'category' => 'Percetakan',
                'icon' => 'fa-solid fa-palette',
                'description' => 'Cetak dokumen berwarna tajam menggunakan inkjet atau laserjet untuk laporan, makalah, dan materi presentasi.',
            ],
            [
                'name' => 'Print Hitam Putih A4/F4',
                'category' => 'Percetakan',
                'icon' => 'fa-solid fa-print',
                'description' => 'Cetak dokumen teks hitam putih cepat dengan kertas HVS standar 70-80 gsm.',
            ],
            [
                'name' => 'Fotokopi Hitam Putih',
                'category' => 'Percetakan',
                'icon' => 'fa-solid fa-copy',
                'description' => 'Penggandaan dokumen, materi kuliah, modul, dan buku perpus bolak-balik maupun satu sisi.',
            ],
            [
                'name' => 'Jilid Lakban & Mika',
                'category' => 'Penjilidan',
                'icon' => 'fa-solid fa-book-open',
                'description' => 'Penjilidan dokumen praktikum dan tugas menggunakan lakban hitam tebal serta mika bening.',
            ],
            [
                'name' => 'Jilid Spiral Kawat & Plastik',
                'category' => 'Penjilidan',
                'icon' => 'fa-solid fa-ring',
                'description' => 'Penjilidan proposal, skripsi seminar, dan company profile dengan ring spiral kuat.',
            ],
            [
                'name' => 'Jilid Hard Cover Skripsi',
                'category' => 'Penjilidan',
                'icon' => 'fa-solid fa-graduation-cap',
                'description' => 'Penjilidan skripsi/tesis standar Universitas Sumatera Utara dengan foil embos emas dan sudut rapi.',
            ],
            [
                'name' => 'Cetak Banner, Poster & Stiker',
                'category' => 'Cetak Khusus',
                'icon' => 'fa-solid fa-image',
                'description' => 'Cetak format besar A3+, Albatros poster seminar, roll-up banner, dan stiker vinyl anti air.',
            ],
            [
                'name' => 'Scan Dokumen ke PDF',
                'category' => 'Digital',
                'icon' => 'fa-solid fa-file-pdf',
                'description' => 'Digitalisasi berkas fisik, ijazah, atau transkrip ke file PDF resolusi 300+ DPI siap kirim email/WA.',
            ],
            [
                'name' => 'Laminating Panas',
                'category' => 'Finishing',
                'icon' => 'fa-solid fa-shield-halved',
                'description' => 'Pelapisan plastik presisi anti air untuk sertifikat, piagam, dan kartu mahasiswa.',
            ],
            [
                'name' => 'Perlengkapan ATK Mahasiswa',
                'category' => 'ATK',
                'icon' => 'fa-solid fa-pen-ruler',
                'description' => 'Penjualan kertas HVS per rim, pulpen, binder, map snelhechter, dan meterai resmi.',
            ],
        ];

        foreach ($services as $data) {
            $data['slug'] = Str::slug($data['name']);
            Service::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}

