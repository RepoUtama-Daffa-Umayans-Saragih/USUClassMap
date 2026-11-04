<?php

namespace App\Repositories\Eloquent;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    public function __construct(Schedule $model)
    {
        parent::__construct($model);
    }

    public function search(?string $keyword = null, ?string $day = null, int $perPage = 15)
    {
        $query = $this->model->with(['course', 'lecturer', 'room.building']);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('course', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                      ->orWhere('code', 'like', "%{$keyword}%");
                })
                ->orWhereHas('lecturer', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            });
        }

        if ($day) {
            $query->where('day', $day);
        }

        return $query->latest()->paginate($perPage);
    }
}
