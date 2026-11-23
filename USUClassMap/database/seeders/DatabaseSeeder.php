<?php

namespace Database\Seeders;

use App\Observers\GitSnapshotObserver;
use App\Services\GitSnapshotSyncService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        GitSnapshotObserver::withoutSync(function () {
            $this->call([
                AdminUserSeeder::class,
                ServiceMasterSeeder::class,
            ]);

            if (is_file(database_path('seeders/snapshots/manifest.json'))) {
                app(GitSnapshotSyncService::class)->importAll(true);

                return;
            }

            $this->call([
                BuildingSeeder::class,
                RoomSeeder::class,
                LecturerSeeder::class,
                CourseSeeder::class,
                ScheduleSeeder::class,
                FibDummySeeder::class,
                FotocopySeeder::class,
                PhotocopySeeder::class,
                MkwuFasilkomSeeder::class,
                MkwuCsvSeeder::class,
            ]);
        });
    }
}
