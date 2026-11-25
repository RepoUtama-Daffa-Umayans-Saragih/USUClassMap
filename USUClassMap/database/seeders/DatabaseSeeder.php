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
                $this->call([
                    FacultyMediaAndDoorsSeeder::class,
                    UsuPressFotocopySeeder::class,
                    RichFotocopyCatalogExpansionSeeder::class,
                    RichFotocopyOrdersDummySeeder::class,
                ]);

                return;
            }

            $this->call([
                BuildingSeeder::class,
                RoomSeeder::class,
                LecturerSeeder::class,
                CourseSeeder::class,
                ScheduleSeeder::class,
                FibDummySeeder::class,
                FacultyMediaAndDoorsSeeder::class,
                FotocopySeeder::class,
                PhotocopySeeder::class,
                TrialAdminFotocopySeeder::class,
                UsuPressFotocopySeeder::class,
                RichFotocopyCatalogExpansionSeeder::class,
                RichFotocopyOrdersDummySeeder::class,
                MkwuFasilkomSeeder::class,
                MkwuCsvSeeder::class,
            ]);
        });
    }
}
