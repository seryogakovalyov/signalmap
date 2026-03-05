<?php

namespace Database\Factories;

use App\Enums\ReportVoteType;
use App\Models\Report;
use App\Models\ReportVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportVote>
 */
class ReportVoteFactory extends Factory
{
    protected $model = ReportVote::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'vote_type' => ReportVoteType::Confirm,
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'browser_id' => fake()->uuid(),
            'created_at' => now(),
        ];
    }

    public function confirm(): static
    {
        return $this->state(fn (): array => [
            'vote_type' => ReportVoteType::Confirm,
        ]);
    }

    public function clear(): static
    {
        return $this->state(fn (): array => [
            'vote_type' => ReportVoteType::Clear,
        ]);
    }
}
