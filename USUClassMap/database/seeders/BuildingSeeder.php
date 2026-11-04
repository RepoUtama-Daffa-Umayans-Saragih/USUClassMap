<?php

namespace Database\Seeders;

use App\Models\Building;
use Illuminate\Database\Seeder;

class BuildingSeeder extends Seeder
{
    /**
     * Run the database seeds strictly from OpenStreetMap (OSM) Overpass API data.
     * Total: 46 authentic buildings from raw_osm_buildings.json.
     */
    public function run(): void
    {
        $osmBuildings = [
            // =================================================================
            // 1. FASILITAS PUSAT & REKTORAT
            // =================================================================
            'Fasilitas Pusat & Rektorat' => [
                'category' => 'Fasilitas Pusat',
                'buildings' => [
                    [
                        'name' => 'Gedung Rektorat USU',
                        'code' => 'REKTORAT',
                        'latitude' => 3.56537960,
                        'longitude' => 98.65682660,
                        'floor_count' => 3,
                        'description' => 'Gedung Biro Pusat Administrasi Rektorat Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Auditorium',
                        'code' => 'AUDITORIUM',
                        'latitude' => 3.56343660,
                        'longitude' => 98.65706790,
                        'floor_count' => 2,
                        'description' => 'Gedung Auditorium Universitas Sumatera Utara untuk acara wisuda, seminar, dan pertemuan akbar.',
                    ],
                    [
                        'name' => 'Perpustakaan USU',
                        'code' => 'PERPUS-USU',
                        'latitude' => 3.55947680,
                        'longitude' => 98.65723150,
                        'floor_count' => 3,
                        'description' => 'Gedung Perpustakaan Pusat Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Gedung Kuliah Bersama',
                        'code' => 'GKB',
                        'latitude' => 3.55807190,
                        'longitude' => 98.65817010,
                        'floor_count' => 4,
                        'description' => 'Gedung Kuliah Bersama (GKB) untuk perkuliahan MKWK dan MKWU antar fakultas.',
                    ],
                    [
                        'name' => 'DLCB',
                        'code' => 'DLCB',
                        'latitude' => 3.56502160,
                        'longitude' => 98.65677580,
                        'floor_count' => 4,
                        'description' => 'Digital Learning & Collaborative Building (DLCB) Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Pusat Sistem Informasi',
                        'code' => 'PSI-USU',
                        'latitude' => 3.56250900,
                        'longitude' => 98.65958420,
                        'floor_count' => 2,
                        'description' => 'Pusat Sistem Informasi (PSI) pengelola infrastruktur IT dan server jaringan USU.',
                    ],
                    [
                        'name' => 'Lembaga Penelitian USU',
                        'code' => 'LPPM-USU',
                        'latitude' => 3.56152710,
                        'longitude' => 98.65874400,
                        'floor_count' => 2,
                        'description' => 'Gedung Lembaga Penelitian dan Pengabdian kepada Masyarakat (LPPM) USU.',
                    ],
                    [
                        'name' => 'USUpress Penerbit',
                        'code' => 'USUPRESS',
                        'latitude' => 3.56222660,
                        'longitude' => 98.65895660,
                        'floor_count' => 2,
                        'description' => 'Pusat percetakan dan penerbitan buku ilmiah Universitas Sumatera Utara Press.',
                    ],
                    [
                        'name' => 'Unit Manajemen Mutu',
                        'code' => 'UMM-USU',
                        'latitude' => 3.56457000,
                        'longitude' => 98.66043420,
                        'floor_count' => 2,
                        'description' => 'Unit Manajemen Mutu penjaminan standar mutu akademik dan pelayanan Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Universitas Sumatera Utara',
                        'code' => 'USU-PUSAT',
                        'latitude' => 3.56166230,
                        'longitude' => 98.65670180,
                        'floor_count' => 3,
                        'description' => 'Pusat landmark dan administrasi koordinasi Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Universitas sumatera Utara',
                        'code' => 'USU-SELATAN',
                        'latitude' => 3.56039330,
                        'longitude' => 98.65650760,
                        'floor_count' => 2,
                        'description' => 'Kompleks perkuliahan Universitas Sumatera Utara (Zona Kampus Selatan).',
                    ],
                ],
            ],

            // =================================================================
            // 2. FAKULTAS ILMU KOMPUTER DAN TEKNOLOGI INFORMASI
            // =================================================================
            'Fakultas Ilmu Komputer dan Teknologi Informasi' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Fakultas Ilmu Komputer dan IT',
                        'code' => 'FASILKOM-TI',
                        'latitude' => 3.56271940,
                        'longitude' => 98.65953750,
                        'floor_count' => 3,
                        'description' => 'Gedung utama Fakultas Ilmu Komputer dan Teknologi Informasi USU.',
                    ],
                    [
                        'name' => 'Gedung B Fasilkomti',
                        'code' => 'FASILKOM-B',
                        'latitude' => 3.56216560,
                        'longitude' => 98.65993070,
                        'floor_count' => 3,
                        'description' => 'Gedung B FASILKOM-TI ruang kelas kuliah dan laboratorium rekayasa perangkat lunak.',
                    ],
                ],
            ],

            // =================================================================
            // 3. FAKULTAS KEDOKTERAN & KESEHATAN
            // =================================================================
            'Fakultas Kedokteran & Kesehatan' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Fakultas Kedokteran',
                        'code' => 'FK',
                        'latitude' => 3.56654600,
                        'longitude' => 98.65928020,
                        'floor_count' => 4,
                        'description' => 'Gedung utama Fakultas Kedokteran Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Fakultas Kedokteran Gigi Universitas Sumatera Utara',
                        'code' => 'FKG',
                        'latitude' => 3.56389360,
                        'longitude' => 98.65840540,
                        'floor_count' => 3,
                        'description' => 'Gedung utama dan administrasi Fakultas Kedokteran Gigi USU.',
                    ],
                    [
                        'name' => 'Fakultas Kesehatan',
                        'code' => 'FKM',
                        'latitude' => 3.56561940,
                        'longitude' => 98.65983600,
                        'floor_count' => 3,
                        'description' => 'Gedung perkuliahan dan administrasi Fakultas Kesehatan Masyarakat USU.',
                    ],
                    [
                        'name' => 'Fakultas Farmasi',
                        'code' => 'FF',
                        'latitude' => 3.56033940,
                        'longitude' => 98.65374270,
                        'floor_count' => 3,
                        'description' => 'Gedung perkuliahan dan laboratorium terpadu Fakultas Farmasi USU.',
                    ],
                ],
            ],

            // =================================================================
            // 4. FAKULTAS TEKNIK
            // =================================================================
            'Fakultas Teknik' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Departamen Teknik Mesin',
                        'code' => 'FT-MESIN',
                        'latitude' => 3.56184900,
                        'longitude' => 98.65504210,
                        'floor_count' => 3,
                        'description' => 'Gedung Departemen dan Laboratorium Teknik Mesin Fakultas Teknik USU.',
                    ],
                    [
                        'name' => 'Teknik Kimia',
                        'code' => 'FT-KIMIA',
                        'latitude' => 3.56256130,
                        'longitude' => 98.65702340,
                        'floor_count' => 3,
                        'description' => 'Gedung Departemen Teknik Kimia Fakultas Teknik Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Gedung Teknik Lingkungan USU',
                        'code' => 'FT-LINGKUNGAN',
                        'latitude' => 3.56033670,
                        'longitude' => 98.65651790,
                        'floor_count' => 3,
                        'description' => 'Gedung Departemen Teknik Lingkungan Fakultas Teknik USU.',
                    ],
                ],
            ],

            // =================================================================
            // 5. FAKULTAS EKONOMI DAN BISNIS
            // =================================================================
            'Fakultas Ekonomi dan Bisnis' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Fakultas Ekonomi dan Bisnes',
                        'code' => 'FEB',
                        'latitude' => 3.55776800,
                        'longitude' => 98.65658920,
                        'floor_count' => 3,
                        'description' => 'Gedung utama perkuliahan Fakultas Ekonomi dan Bisnis Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Magister Manajemen USU',
                        'code' => 'MM-FEB',
                        'latitude' => 3.56443160,
                        'longitude' => 98.65811190,
                        'floor_count' => 3,
                        'description' => 'Gedung perkuliahan program pascasarjana Magister Manajemen FEB USU.',
                    ],
                ],
            ],

            // =================================================================
            // 6. FAKULTAS HUKUM & ILMU SOSIAL
            // =================================================================
            'Fakultas Hukum & Ilmu Sosial' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Fakultas Hukum Universitas Sumatera Utara',
                        'code' => 'FH',
                        'latitude' => 3.55836110,
                        'longitude' => 98.65973460,
                        'floor_count' => 3,
                        'description' => 'Gedung utama Fakultas Hukum Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Fakultas Ilmu Sosial dan Ilmu Politik',
                        'code' => 'FISIP',
                        'latitude' => 3.55687520,
                        'longitude' => 98.65705570,
                        'floor_count' => 3,
                        'description' => 'Gedung perkuliahan dan administrasi Fakultas Ilmu Sosial dan Ilmu Politik USU.',
                    ],
                    [
                        'name' => 'Tax Center Fisip',
                        'code' => 'TAX-CENTER',
                        'latitude' => 3.55666790,
                        'longitude' => 98.65651730,
                        'floor_count' => 2,
                        'description' => 'Pusat konsultasi perpajakan dan riset fiskal FISIP USU.',
                    ],
                    [
                        'name' => 'Magister Studi Pembangunan MSP',
                        'code' => 'MSP-FISIP',
                        'latitude' => 3.55860110,
                        'longitude' => 98.65638610,
                        'floor_count' => 2,
                        'description' => 'Gedung program pascasarjana Magister Studi Pembangunan USU.',
                    ],
                ],
            ],

            // =================================================================
            // 7. FAKULTAS LAINNYA & VOKASI
            // =================================================================
            'Fakultas Lainnya & Vokasi' => [
                'category' => 'Fakultas',
                'buildings' => [
                    [
                        'name' => 'Fakultas Ilmu Budaya Universitas Sumatera Utara',
                        'code' => 'FIB',
                        'latitude' => 3.55993230,
                        'longitude' => 98.65989410,
                        'floor_count' => 3,
                        'description' => 'Gedung perkuliahan dan administrasi Fakultas Ilmu Budaya USU.',
                    ],
                    [
                        'name' => 'Fakultas Psikologi',
                        'code' => 'FPSI',
                        'latitude' => 3.56662280,
                        'longitude' => 98.65798690,
                        'floor_count' => 3,
                        'description' => 'Gedung Dekanat dan ruang kuliah Fakultas Psikologi Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'Fakultas Keteknikan Pertanian',
                        'code' => 'FP-TEKNIK',
                        'latitude' => 3.55827770,
                        'longitude' => 98.65547950,
                        'floor_count' => 2,
                        'description' => 'Gedung Departemen Keteknikan Pertanian Fakultas Pertanian USU.',
                    ],
                    [
                        'name' => 'Fakultas Vokasi',
                        'code' => 'VOKASI',
                        'latitude' => 3.55822250,
                        'longitude' => 98.65401570,
                        'floor_count' => 3,
                        'description' => 'Gedung Dekanat dan ruang kuliah terpadu Fakultas Vokasi USU.',
                    ],
                    [
                        'name' => 'Perpustakaan Cabang MIPA - Universitas Sumatera Utara',
                        'code' => 'PERPUS-MIPA',
                        'latitude' => 3.56044650,
                        'longitude' => 98.65453520,
                        'floor_count' => 2,
                        'description' => 'Perpustakaan cabang FMIPA penyedia literatur sains dan teknologi.',
                    ],
                ],
            ],

            // =================================================================
            // 8. GEDUNG PERKANTORAN & ADMINISTRASI
            // =================================================================
            'Gedung Perkantoran & Administrasi' => [
                'category' => 'Administrasi',
                'buildings' => [
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-1',
                        'latitude' => 3.56703530,
                        'longitude' => 98.65564610,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor administrasi unit pelaksana teknis kampus USU (Zona Utara).',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-2',
                        'latitude' => 3.56599610,
                        'longitude' => 98.65495220,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor pelayanan akademik dan tata usaha unit kampus USU.',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-3',
                        'latitude' => 3.56631420,
                        'longitude' => 98.65545640,
                        'floor_count' => 2,
                        'description' => 'Gedung perkantoran pengelola fasilitas dan sarana prasarana USU.',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-4',
                        'latitude' => 3.56690270,
                        'longitude' => 98.65569540,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor layanan administrasi pendukung perkuliahan USU.',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-5',
                        'latitude' => 3.56679820,
                        'longitude' => 98.65543540,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor staf dan pengelola operasional kampus USU.',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-6',
                        'latitude' => 3.56664310,
                        'longitude' => 98.65548040,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor layanan umum dan urusan rumah tangga kampus USU.',
                    ],
                    [
                        'name' => 'KANTOR',
                        'code' => 'KANTOR-ADM-7',
                        'latitude' => 3.56703060,
                        'longitude' => 98.65488060,
                        'floor_count' => 2,
                        'description' => 'Gedung kantor pengawasan operasional dan pemeliharaan gedung USU.',
                    ],
                ],
            ],

            // =================================================================
            // 9. FASILITAS IBADAH
            // =================================================================
            'Fasilitas Ibadah' => [
                'category' => 'Fasilitas Ibadah',
                'buildings' => [
                    [
                        'name' => "Masjid As-Syifa' Fakultas Farmasi USU",
                        'code' => 'MSJ-ASSYIFA',
                        'latitude' => 3.56082580,
                        'longitude' => 98.65367630,
                        'floor_count' => 2,
                        'description' => "Masjid As-Syifa' di lingkungan Fakultas Farmasi Universitas Sumatera Utara.",
                    ],
                    [
                        'name' => 'Masjid Al-Ikhlas FKG USU',
                        'code' => 'MSJ-ALIKHLAS',
                        'latitude' => 3.56315820,
                        'longitude' => 98.65796750,
                        'floor_count' => 2,
                        'description' => 'Masjid Al-Ikhlas di lingkungan Fakultas Kedokteran Gigi USU.',
                    ],
                    [
                        'name' => 'Gereja GBKP Majelis Runggun Pembangunan',
                        'code' => 'GBKP-PEMBANGUNAN',
                        'latitude' => 3.56277660,
                        'longitude' => 98.65244560,
                        'floor_count' => 2,
                        'description' => 'Gereja GBKP Majelis Runggun Pembangunan di kawasan kampus USU.',
                    ],
                ],
            ],

            // =================================================================
            // 10. FASILITAS PENUNJANG & MITRA KAMPUS
            // =================================================================
            'Fasilitas Penunjang & Mitra Kampus' => [
                'category' => 'Fasilitas Penunjang',
                'buildings' => [
                    [
                        'name' => 'Kantin',
                        'code' => 'KANTIN-FISIP',
                        'latitude' => 3.55769390,
                        'longitude' => 98.65708860,
                        'floor_count' => 1,
                        'description' => 'Kantin sentra kuliner mahasiswa di area FISIP USU.',
                    ],
                    [
                        'name' => 'Kantin FP',
                        'code' => 'KANTIN-FP',
                        'latitude' => 3.55811540,
                        'longitude' => 98.65549740,
                        'floor_count' => 1,
                        'description' => 'Kantin mahasiswa Fakultas Pertanian Universitas Sumatera Utara.',
                    ],
                    [
                        'name' => 'TEMPAT DUDUK',
                        'code' => 'GAZEBO-TAMAN',
                        'latitude' => 3.56606480,
                        'longitude' => 98.65379870,
                        'floor_count' => 1,
                        'description' => 'Area gazebo dan fasilitas ruang terbuka tempat duduk mahasiswa USU.',
                    ],
                    [
                        'name' => 'Politeknik Negeri Medan',
                        'code' => 'POLMED',
                        'latitude' => 3.56327590,
                        'longitude' => 98.65358390,
                        'floor_count' => 4,
                        'description' => 'Gedung perkuliahan Politeknik Negeri Medan di dalam kompleks kampus USU.',
                    ],
                    [
                        'name' => 'OYO 1183 Papakoel',
                        'code' => 'PAPAKOEL',
                        'latitude' => 3.56452530,
                        'longitude' => 98.65235080,
                        'floor_count' => 3,
                        'description' => 'Akomodasi dan fasilitas penginapan di sekitar kawasan kampus USU.',
                    ],
                ],
            ],
        ];

        // Insert exactly the 46 OSM buildings into the database
        foreach ($osmBuildings as $groupName => $group) {
            $category = $group['category'];
            foreach ($group['buildings'] as $b) {
                Building::updateOrCreate(
                    ['code' => $b['code']],
                    [
                        'name' => $b['name'],
                        'code' => $b['code'],
                        'faculty' => $groupName,
                        'category' => $category,
                        'latitude' => $b['latitude'],
                        'longitude' => $b['longitude'],
                        'description' => $b['description'],
                        'floor_count' => $b['floor_count'],
                    ]
                );
            }
        }
    }
}
