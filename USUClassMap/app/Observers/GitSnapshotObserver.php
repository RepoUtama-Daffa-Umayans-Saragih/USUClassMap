<?php

namespace App\Observers;

use App\Models\Building;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\GitSnapshotSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class GitSnapshotObserver
{
    /**
     * Flag mute untuk menonaktifkan auto-export saat operasi batch (mis. import Excel / snapshot import).
     */
    public static bool $isMuted = false;

    /**
     * Daftar model yang berdampak pada graf RDF (schedules.rdf).
     */
    protected const RDF_RELEVANT_MODELS = [
        Building::class,
        Room::class,
        Course::class,
        Lecturer::class,
        Schedule::class,
    ];

    /**
     * Jalankan callback tanpa memicu auto-export pada setiap baris model.
     */
    public static function withoutSync(callable $callback): mixed
    {
        $previous = self::$isMuted;
        self::$isMuted = true;

        try {
            return $callback();
        } finally {
            self::$isMuted = $previous;
        }
    }

    /**
     * Handle event "saved" (created & updated).
     */
    public function saved(Model $model): void
    {
        $this->triggerSnapshotExport($model);
    }

    /**
     * Handle event "deleted".
     */
    public function deleted(Model $model): void
    {
        $this->triggerSnapshotExport($model);
    }

    protected function triggerSnapshotExport(Model $model): void
    {
        if (self::$isMuted || app()->runningUnitTests()) {
            return;
        }

        try {
            $regenerateRdf = in_array($model::class, self::RDF_RELEVANT_MODELS, true);
            app(GitSnapshotSyncService::class)->exportAll($regenerateRdf);
        } catch (\Throwable $e) {
            Log::warning('GitSnapshotObserver: Gagal mengekspor snapshot Git secara otomatis: ' . $e->getMessage(), [
                'model' => $model::class,
                'id' => $model->getKey(),
            ]);
        }
    }
}
