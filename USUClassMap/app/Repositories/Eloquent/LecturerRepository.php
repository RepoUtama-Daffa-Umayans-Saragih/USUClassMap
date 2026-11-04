<?php

namespace App\Repositories\Eloquent;

use App\Models\Lecturer;
use App\Repositories\Contracts\LecturerRepositoryInterface;

class LecturerRepository extends BaseRepository implements LecturerRepositoryInterface
{
    public function __construct(Lecturer $model)
    {
        parent::__construct($model);
    }

    public function search(?string $keyword = null, int $perPage = 15)
    {
        $query = $this->model->withCount('schedules');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('nip', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }
}
