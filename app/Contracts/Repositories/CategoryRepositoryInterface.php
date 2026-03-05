<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function all(): Collection;
}
