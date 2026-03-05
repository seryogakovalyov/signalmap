<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Contracts\View\View;

class MapPageController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function __invoke(): View
    {
        $categories = $this->categories->listForSelection();

        return view('map', [
            'categories' => $categories,
            'categoryOptions' => $categories
                ->map(fn ($category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                ])
                ->values()
                ->all(),
        ]);
    }
}
