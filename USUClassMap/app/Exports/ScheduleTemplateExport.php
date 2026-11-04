<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ScheduleTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'kode_mk',
            'nama_mk',
            'sks',
            'tipe_mk',
            'nama_dosen',
            'nama_ruangan',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'semester',
            'kelompok_kelas'
        ];
    }

    public function array(): array
    {
        return [
            ['UNI101', 'Pendidikan Agama Islam', 2, 'MKWU', 'Dr. H. Ahmad Fauzi, M.A.', 'GK1-101', 'Senin', '08:00', '09:40', 'Ganjil 2024/2025', 'A'],
            ['UNI102', 'Pendidikan Pancasila', 2, 'MKWU', 'Prof. Dr. Siti Rahmah, S.H., M.Hum.', 'GK1-102', 'Selasa', '10:00', '11:40', 'Ganjil 2024/2025', 'B'],
            ['UNI103', 'Bahasa Indonesia', 2, 'MKWU', 'Drs. Budi Santoso, M.Pd.', 'GK1-103', 'Rabu', '13:00', '14:40', 'Ganjil 2024/2025', 'C'],
        ];
    }
}
