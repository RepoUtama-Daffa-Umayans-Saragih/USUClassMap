<?php

namespace App\Repositories\Eloquent;

use App\Models\Building;
use App\Repositories\Contracts\BuildingRepositoryInterface;

class BuildingRepository extends BaseRepository implements BuildingRepositoryInterface
{
    public function __construct(Building $model)
    {
        parent::__construct($model);
    }

    public function search(?string $keyword = null, int $perPage = 15)
    {
        $query = $this->model->withCount('rooms');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }
}
