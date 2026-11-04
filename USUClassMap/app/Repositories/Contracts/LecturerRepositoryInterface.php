<?php

namespace App\Repositories\Contracts;

interface LecturerRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $keyword = null, int $perPage = 15);
}
