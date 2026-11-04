<?php

namespace App\Repositories\Contracts;

interface CourseRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $keyword = null, int $perPage = 15);
}
