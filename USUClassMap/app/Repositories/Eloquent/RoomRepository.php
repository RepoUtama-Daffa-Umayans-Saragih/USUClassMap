<?php

namespace App\Repositories\Eloquent;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;

class RoomRepository extends BaseRepository implements RoomRepositoryInterface
{
    public function __construct(Room $model)
    {
        parent::__construct($model);
    }

    public function search(?string $keyword = null, int $perPage = 15)
    {
        $query = $this->model->with('building')->withCount('schedules');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhereHas('building', function ($q) use ($keyword) {
                      $q->where('name', 'like', "%{$keyword}%");
                  });
            });
        }

        return $query->latest()->paginate($perPage);
    }
}
