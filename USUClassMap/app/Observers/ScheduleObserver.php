<?php

namespace App\Observers;

use App\Jobs\RegenerateRdfJob;
use App\Models\Schedule;

class ScheduleObserver
{
    /**
     * Handle the Schedule "created" event.
     */
    public function created(Schedule $schedule): void
    {
        RegenerateRdfJob::dispatch();
    }

    /**
     * Handle the Schedule "updated" event.
     */
    public function updated(Schedule $schedule): void
    {
        RegenerateRdfJob::dispatch();
    }

    /**
     * Handle the Schedule "deleted" event.
     */
    public function deleted(Schedule $schedule): void
    {
        RegenerateRdfJob::dispatch();
    }
}
