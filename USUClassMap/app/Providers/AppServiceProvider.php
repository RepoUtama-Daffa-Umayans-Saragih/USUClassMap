<?php

namespace App\Providers;

use App\Models\Building;
use App\Models\CampusRoutingOverride;
use App\Models\Course;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use App\Models\Lecturer;
use App\Models\PhotocopyItem;
use App\Models\PhotocopyPlace;
use App\Models\Room;
use App\Models\RoomMedia;
use App\Models\Schedule;
use App\Observers\GitSnapshotObserver;
use App\Observers\ScheduleObserver;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\LecturerRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Eloquent\BuildingRepository;
use App\Repositories\Eloquent\CourseRepository;
use App\Repositories\Eloquent\LecturerRepository;
use App\Repositories\Eloquent\RoomRepository;
use App\Repositories\Eloquent\ScheduleRepository;
use App\Services\GitSnapshotSyncService;
use App\Services\RdfGeneratorService;
use App\Services\SparqlService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repositories
        $this->app->bind(BuildingRepositoryInterface::class, BuildingRepository::class);
        $this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);
        $this->app->bind(LecturerRepositoryInterface::class, LecturerRepository::class);
        $this->app->bind(CourseRepositoryInterface::class, CourseRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);

        // Semantic Web & SPARQL Services
        $this->app->singleton(RdfGeneratorService::class, function () {
            return new RdfGeneratorService();
        });

        $this->app->singleton(SparqlService::class, function () {
            return new SparqlService();
        });

        // Auto-Sync Git Snapshot Service
        $this->app->singleton(GitSnapshotSyncService::class, function () {
            return new GitSnapshotSyncService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // Model Observers
        Schedule::observe(ScheduleObserver::class);

        // Auto-Sync Git Snapshot Observers (10 model CRUD utama)
        Building::observe(GitSnapshotObserver::class);
        Room::observe(GitSnapshotObserver::class);
        RoomMedia::observe(GitSnapshotObserver::class);
        Course::observe(GitSnapshotObserver::class);
        Lecturer::observe(GitSnapshotObserver::class);
        Schedule::observe(GitSnapshotObserver::class);
        FotocopyStore::observe(GitSnapshotObserver::class);
        PhotocopyPlace::observe(GitSnapshotObserver::class);
        FotocopyService::observe(GitSnapshotObserver::class);
        PhotocopyItem::observe(GitSnapshotObserver::class);
        CampusRoutingOverride::observe(GitSnapshotObserver::class);
    }
}
