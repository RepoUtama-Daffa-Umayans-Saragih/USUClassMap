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
        // 1. Akun Super Admin (Universal Access ke seluruh panel sistem)
        User::updateOrCreate(
            ['email' => 'superadmin@usu.ac.id'],
            [
                'name' => 'Super Admin USUClassMap',
                'nim' => 'superadmin',
                'semester' => 'Super Administrator',
                'password' => bcrypt('superadmin123'),
                'role' => 'superadmin',
            ]
        );

        // 2. Akun Admin USUClassMap
        User::updateOrCreate(
            ['email' => 'adminususclassmapi'],
            [
                'name' => 'Admin USUClassMap',
                'nim' => 'adminususclassmapi',
                'semester' => 'Staff',
                'password' => bcrypt('adminusu123'),
                'role' => 'admin_usuclassmap',
            ]
        );

        // Akun admin tambahan untuk fleksibilitas testing / fallback
        User::updateOrCreate(
            ['email' => 'admin@usu.ac.id'],
            [
                'name' => 'Admin USUClassMap',
                'nim' => 'ADM002',
                'semester' => 'Staff',
                'password' => bcrypt('adminusu123'),
                'role' => 'admin_usuclassmap',
            ]
        );

        // 2. Demo Mahasiswa User (SATU USU Identity)
        User::updateOrCreate(
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
