<?php

namespace Database\Seeders;

use App\Models\Lecturer;
use Illuminate\Database\Seeder;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $lecturers = [
            [
                'name' => 'Prof. Dr. H. M. Arif Nasution, M.A.',
                'nip' => '196504151990031002',
                'email' => 'arif.nasution@usu.ac.id',
                'phone' => '081260112233',
            ],
            [
                'name' => 'Dr. Hj. Siti Rahmah, S.H., M.Hum.',
                'nip' => '197008201995122001',
                'email' => 'siti.rahmah@usu.ac.id',
                'phone' => '081370223344',
            ],
            [
                'name' => 'Dr. Drs. Budi Santoso, M.Pd.',
                'nip' => '197511102001121003',
                'email' => 'budi.santoso@usu.ac.id',
                'phone' => '082165334455',
            ],
            [
                'name' => 'Dra. Nur Asiah, M.Si.',
                'nip' => '197803252003122002',
                'email' => 'nur.asiah@usu.ac.id',
                'phone' => '085261445566',
            ],
            [
                'name' => 'Rahmat Hidayat, S.Kom., M.Sc.',
                'nip' => '198806122015041001',
                'email' => 'rahmat.hidayat@usu.ac.id',
                'phone' => '081265556677',
            ],
            [
                'name' => 'Dr. Ir. Henry Sitompul, M.T.',
                'nip' => '196812041994031001',
                'email' => 'henry.sitompul@usu.ac.id',
                'phone' => '081397667788',
            ],
            [
                'name' => 'Dra. Marlina Siregar, M.Hum.',
                'nip' => '197209141998022001',
                'email' => 'marlina.siregar@usu.ac.id',
                'phone' => '082273778899',
            ],
            [
                'name' => 'Faisal Reza, S.T., M.Kom.',
                'nip' => '199002182019031008',
                'email' => 'faisal.reza@usu.ac.id',
                'phone' => '085362889900',
            ],
            [
                'name' => 'Prof. Dr. Opim Salim Sitompul, M.Sc.',
                'nip' => '196208101988031002',
                'email' => 'opim@usu.ac.id',
                'phone' => '081160889911',
            ],
            [
                'name' => 'Dr. Erna Budhiarti Nababan, M.IT.',
                'nip' => '196903221994032001',
                'email' => 'erna.nababan@usu.ac.id',
                'phone' => '081260447788',
            ],
        ];

        foreach ($lecturers as $l) {
            Lecturer::firstOrCreate(['nip' => $l['nip']], $l);
        }
    }
}
