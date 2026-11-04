<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            // MKWU (Mata Kuliah Wajib Universitas)
            ['code' => 'UNI101', 'name' => 'Pendidikan Agama Islam', 'credits' => 2, 'type' => 'MKWU'],
            ['code' => 'UNI102', 'name' => 'Pendidikan Agama Kristen', 'credits' => 2, 'type' => 'MKWU'],
            ['code' => 'UNI103', 'name' => 'Pendidikan Agama Katolik', 'credits' => 2, 'type' => 'MKWU'],
            ['code' => 'UNI104', 'name' => 'Pendidikan Pancasila', 'credits' => 2, 'type' => 'MKWU'],
            ['code' => 'UNI105', 'name' => 'Pendidikan Kewarganegaraan', 'credits' => 2, 'type' => 'MKWU'],
            ['code' => 'UNI106', 'name' => 'Bahasa Indonesia', 'credits' => 2, 'type' => 'MKWU'],

            // MKWK (Mata Kuliah Wajib Kurikulum)
            ['code' => 'UNI107', 'name' => 'Bahasa Inggris Akademik', 'credits' => 2, 'type' => 'MKWK'],
            ['code' => 'UNI108', 'name' => 'Kewirausahaan Berbasis IPTEK', 'credits' => 2, 'type' => 'MKWK'],
            ['code' => 'UNI109', 'name' => 'Kearifan Lokal dan Budaya Sumatera Utara', 'credits' => 2, 'type' => 'MKWK'],
            ['code' => 'UNI110', 'name' => 'Etika dan Literasi Digital', 'credits' => 2, 'type' => 'MKWK'],

            // Program Studi (Ilmu Komputer / TI)
            ['code' => 'KOM201', 'name' => 'Web Semantik & Ontologi', 'credits' => 3, 'type' => 'Prodi'],
            ['code' => 'KOM202', 'name' => 'Sistem Basis Data', 'credits' => 3, 'type' => 'Prodi'],
            ['code' => 'KOM203', 'name' => 'Rekayasa Perangkat Lunak', 'credits' => 3, 'type' => 'Prodi'],
            ['code' => 'KOM204', 'name' => 'Kecerdasan Buatan & Sistem Pakar', 'credits' => 3, 'type' => 'Prodi'],
            ['code' => 'KOM205', 'name' => 'Pengembangan Aplikasi Web Modern', 'credits' => 3, 'type' => 'Prodi'],
        ];

        foreach ($courses as $c) {
            Course::firstOrCreate(['code' => $c['code']], $c);
        }
    }
}
