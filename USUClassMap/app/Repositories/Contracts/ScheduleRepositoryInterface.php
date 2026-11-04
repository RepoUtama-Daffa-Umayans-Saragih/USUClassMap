<?php

namespace App\Repositories\Contracts;

interface ScheduleRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $keyword = null, ?string $day = null, int $perPage = 15);
}
