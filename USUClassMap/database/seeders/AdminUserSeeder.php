<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user and demo student user.
     */
    public function run(): void
    {
        // 1. Super Admin User
        User::firstOrCreate(
            ['email' => 'admin@usu.ac.id'],
            [
                'name' => 'Super Admin',
                'nim' => 'ADM001',
                'semester' => 'Staff',
                'password' => bcrypt('Admin123!'),
                'role' => 'superadmin',
            ]
        );

        // 2. Demo Mahasiswa User (SATU USU Identity)
        User::firstOrCreate(
            ['email' => 'daffa@students.usu.ac.id'],
            [
                'name' => 'Daffa Umayans Saragih',
                'nim' => '251402011',
                'semester' => '3',
                'password' => bcrypt('Password123!'),
                'role' => 'user',
            ]
        );
    }
}
