<?php

namespace App\Providers;

use App\Models\Schedule;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // Model Observers
        Schedule::observe(ScheduleObserver::class);
    }
}
