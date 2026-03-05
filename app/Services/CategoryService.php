<?php

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function listForSelection(): Collection
    {
        return $this->categories->all();
    }
}
