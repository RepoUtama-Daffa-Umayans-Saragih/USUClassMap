<?php

namespace App\Repositories\Contracts;

interface BuildingRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $keyword = null, int $perPage = 15);
}
