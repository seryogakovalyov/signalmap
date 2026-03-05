<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'latitude' => fake()->latitude(50.35, 50.55),
            'longitude' => fake()->longitude(30.30, 30.70),
            'category_id' => Category::factory(),
            'reporter_ip_hash' => hash('sha256', fake()->ipv4()),
            'reporter_browser_id' => fake()->uuid(),
            'confirmations_count' => 0,
            'clear_votes_count' => 0,
            'status' => ReportStatus::Unverified,
        ];
    }

    public function partiallyConfirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmations_count' => 1,
            'status' => ReportStatus::PartiallyConfirmed,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'confirmations_count' => 3,
            'status' => ReportStatus::Confirmed,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'clear_votes_count' => 3,
            'status' => ReportStatus::Resolved,
        ]);
    }
}
