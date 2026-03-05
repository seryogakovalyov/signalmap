<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            DemoReportsSeeder::class,
        ]);

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@lab.test')],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
            ],
        );
    }
}
