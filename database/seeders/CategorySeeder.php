<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Infrastructure', 'color' => '#dc2626'],
            ['name' => 'Safety', 'color' => '#ea580c'],
            ['name' => 'Environment', 'color' => '#16a34a'],
            ['name' => 'Traffic', 'color' => '#2563eb'],
            ['name' => 'Community', 'color' => '#7c3aed'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['name' => $category['name']],
                ['color' => $category['color']],
            );
        }
    }
}
