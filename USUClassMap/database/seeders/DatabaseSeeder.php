<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            BuildingSeeder::class,
            RoomSeeder::class,
            LecturerSeeder::class,
            CourseSeeder::class,
            ScheduleSeeder::class,
            FibDummySeeder::class,
        ]);
    }
}
