<?php

namespace App\Repositories\Contracts;

interface RoomRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $keyword = null, int $perPage = 15);
}
