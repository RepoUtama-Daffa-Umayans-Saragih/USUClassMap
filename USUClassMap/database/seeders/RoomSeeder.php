<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $buildings = Building::all()->keyBy('code');

        $rooms = [
            // ================= FASILITAS PUSAT =================
            ['building_code' => 'GK1', 'name' => 'GK1-101', 'floor' => 1, 'capacity' => 60],
            ['building_code' => 'GK1', 'name' => 'GK1-102', 'floor' => 1, 'capacity' => 60],
            ['building_code' => 'GK1', 'name' => 'GK1-201', 'floor' => 2, 'capacity' => 65],
            ['building_code' => 'GK1', 'name' => 'GK1-202', 'floor' => 2, 'capacity' => 65],
            ['building_code' => 'GK1', 'name' => 'GK1-301', 'floor' => 3, 'capacity' => 75],

            ['building_code' => 'GK2', 'name' => 'GK2-101', 'floor' => 1, 'capacity' => 55],
            ['building_code' => 'GK2', 'name' => 'GK2-201', 'floor' => 2, 'capacity' => 55],
            ['building_code' => 'GK2', 'name' => 'GK2-301', 'floor' => 3, 'capacity' => 70],
            ['building_code' => 'GK2', 'name' => 'Lab Bahasa GK2', 'floor' => 4, 'capacity' => 40],

            ['building_code' => 'PERPUS-PUSAT', 'name' => 'Ruang Diskusi Lantai 2', 'floor' => 2, 'capacity' => 35],
            ['building_code' => 'PERPUS-PUSAT', 'name' => 'Digital Learning Theater', 'floor' => 3, 'capacity' => 90],

            ['building_code' => 'AUDITORIUM', 'name' => 'Auditorium Utama', 'floor' => 1, 'capacity' => 500],
            ['building_code' => 'AUDITORIUM', 'name' => 'Ruang Sidang Pleno', 'floor' => 2, 'capacity' => 120],

            ['building_code' => 'PUSAT-BAHASA', 'name' => 'Lab TOEFL & IELTS', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'CIE-USU', 'name' => 'Coworking Space Inovasi', 'floor' => 1, 'capacity' => 50],

            // ================= FASILKOM-TI =================
            ['building_code' => 'FASILKOM-A', 'name' => 'Lab Software Engineering', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'FASILKOM-A', 'name' => 'Ruang Kuliah TI-201', 'floor' => 2, 'capacity' => 50],
            ['building_code' => 'FASILKOM-A', 'name' => 'Lab Jaringan & Cloud', 'floor' => 2, 'capacity' => 40],
            ['building_code' => 'FASILKOM-B', 'name' => 'Lab Artificial Intelligence', 'floor' => 2, 'capacity' => 45],
            ['building_code' => 'FASILKOM-B', 'name' => 'Ruang Seminar TI-301', 'floor' => 3, 'capacity' => 80],

            // ================= KEDOKTERAN & RS USU =================
            ['building_code' => 'FK-KULIAH', 'name' => 'Amphitheater FK Lt.1', 'floor' => 1, 'capacity' => 120],
            ['building_code' => 'FK-KULIAH', 'name' => 'Ruang Tutorial PBL 101', 'floor' => 2, 'capacity' => 20],
            ['building_code' => 'FK-LAB', 'name' => 'Lab Anatomi & Skills Lab', 'floor' => 1, 'capacity' => 50],
            ['building_code' => 'RS-USU', 'name' => 'Ruang Kuliah Koas RS USU', 'floor' => 3, 'capacity' => 50],

            // ================= HUKUM =================
            ['building_code' => 'FH-DEKANAT', 'name' => 'Ruang Sidang Semu Mochtar Kusumaatmadja', 'floor' => 2, 'capacity' => 80],
            ['building_code' => 'FH-KULIAH', 'name' => 'Ruang Kuliah FH-101', 'floor' => 1, 'capacity' => 60],

            // ================= PERTANIAN =================
            ['building_code' => 'FP-KULIAH', 'name' => 'Ruang Kuliah FP-101', 'floor' => 1, 'capacity' => 50],
            ['building_code' => 'FP-LAB', 'name' => 'Lab Ilmu Tanah & Kultur Jaringan', 'floor' => 1, 'capacity' => 35],

            // ================= TEKNIK =================
            ['building_code' => 'FT-MESIN-IND', 'name' => 'Lab Ergonomi & Manufaktur', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'FT-ELEKTRO', 'name' => 'Lab Sistem Telekomunikasi', 'floor' => 2, 'capacity' => 40],
            ['building_code' => 'FT-SIPIL', 'name' => 'Lab Mekanika Tanah & Aspal', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'FT-KIMIA-ARS', 'name' => 'Studio Gambar Arsitektur', 'floor' => 2, 'capacity' => 50],

            // ================= EKONOMI & BISNIS =================
            ['building_code' => 'FEB-DEKANAT', 'name' => 'Galeri Investasi BEI FEB', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'FEB-KULIAH', 'name' => 'Ruang Kuliah FEB-101', 'floor' => 1, 'capacity' => 55],
            ['building_code' => 'FEB-KULIAH', 'name' => 'Ruang Kuliah FEB-201', 'floor' => 2, 'capacity' => 60],

            // ================= KEDOKTERAN GIGI =================
            ['building_code' => 'FKG-DEKANAT', 'name' => 'Ruang Kuliah FKG-101', 'floor' => 1, 'capacity' => 50],
            ['building_code' => 'RSGM-USU', 'name' => 'Dental Pre-Clinic Simulation Lab', 'floor' => 2, 'capacity' => 40],

            // ================= ILMU BUDAYA =================
            ['building_code' => 'FIB-KULIAH', 'name' => 'Ruang Teater Mini FIB', 'floor' => 2, 'capacity' => 70],
            ['building_code' => 'FIB-KULIAH', 'name' => 'Ruang Kuliah FIB-101', 'floor' => 1, 'capacity' => 50],

            // ================= FMIPA =================
            ['building_code' => 'FMIPA-FIS-KIM', 'name' => 'Lab Instrumentasi & Kimia Analitik', 'floor' => 2, 'capacity' => 35],
            ['building_code' => 'FMIPA-BIO-MAT', 'name' => 'Ruang Kuliah Statistika FMIPA', 'floor' => 1, 'capacity' => 50],

            // ================= FISIP =================
            ['building_code' => 'FISIP-KULIAH', 'name' => 'Lab Radio & Broadcasting FISIP', 'floor' => 2, 'capacity' => 35],
            ['building_code' => 'FISIP-KULIAH', 'name' => 'Ruang Kuliah FISIP-101', 'floor' => 1, 'capacity' => 55],

            // ================= FKM & PSIKOLOGI =================
            ['building_code' => 'FKM-KULIAH', 'name' => 'Lab Biostatistik & Gizi FKM', 'floor' => 1, 'capacity' => 40],
            ['building_code' => 'FPSI-LAB', 'name' => 'Ruang Observasi & Konseling Anak', 'floor' => 1, 'capacity' => 25],

            // ================= KEHUTANAN, FARMASI, KEPERAWATAN, VOKASI =================
            ['building_code' => 'FAHUTAN-KUL', 'name' => 'Lab Dendrologi & Ekologi Hutan', 'floor' => 1, 'capacity' => 35],
            ['building_code' => 'FF-LAB', 'name' => 'Lab Formulasi Sediaan Farmasi', 'floor' => 2, 'capacity' => 40],
            ['building_code' => 'FKEP-LAB', 'name' => 'Mini Hospital Keperawatan KMB', 'floor' => 1, 'capacity' => 30],
            ['building_code' => 'VOKASI-LAB', 'name' => 'Lab Simulasi Perbankan Syariah Vokasi', 'floor' => 2, 'capacity' => 45],
        ];

        foreach ($rooms as $r) {
            $building = $buildings[$r['building_code']] ?? null;
            if ($building) {
                Room::firstOrCreate(
                    [
                        'building_id' => $building->id,
                        'name' => $r['name'],
                    ],
                    [
                        'building_id' => $building->id,
                        'name' => $r['name'],
                        'floor' => $r['floor'],
                        'capacity' => $r['capacity'],
                    ]
                );
            }
        }
    }
}
