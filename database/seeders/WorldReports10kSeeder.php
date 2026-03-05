<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorldReports10kSeeder extends Seeder
{
    private const TOTAL_REPORTS = 100000;
    private const BATCH_SIZE = 1000;

    /**
     * @var array<int, array{lat: float, lng: float}>
     */
    private const WORLD_HUBS = [
        ['lat' => 40.7128, 'lng' => -74.0060],   // New York
        ['lat' => 34.0522, 'lng' => -118.2437],  // Los Angeles
        ['lat' => 51.5074, 'lng' => -0.1278],    // London
        ['lat' => 48.8566, 'lng' => 2.3522],     // Paris
        ['lat' => 52.5200, 'lng' => 13.4050],    // Berlin
        ['lat' => 50.4501, 'lng' => 30.5234],    // Kyiv
        ['lat' => 49.987, 'lng' => 36.262],    // Kharkiv
        ['lat' => 35.6895, 'lng' => 139.6917],   // Tokyo
        ['lat' => 37.5665, 'lng' => 126.9780],   // Seoul
        ['lat' => 1.3521, 'lng' => 103.8198],    // Singapore
        ['lat' => -33.8688, 'lng' => 151.2093],  // Sydney
        ['lat' => 19.4326, 'lng' => -99.1332],   // Mexico City
        ['lat' => -23.5505, 'lng' => -46.6333],  // Sao Paulo
        ['lat' => -34.6037, 'lng' => -58.3816],  // Buenos Aires
        ['lat' => 30.0444, 'lng' => 31.2357],    // Cairo
        ['lat' => -1.2921, 'lng' => 36.8219],    // Nairobi
        ['lat' => 28.6139, 'lng' => 77.2090],    // New Delhi
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $categoryIds = Category::query()->pluck('id')->all();

        if ($categoryIds === []) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('report_votes')->truncate();
        DB::table('reports')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = Carbon::now();
        $remaining = self::TOTAL_REPORTS;

        while ($remaining > 0) {
            $batchSize = min(self::BATCH_SIZE, $remaining);
            $rows = [];

            for ($index = 0; $index < $batchSize; $index++) {
                $hub = self::WORLD_HUBS[array_rand(self::WORLD_HUBS)];
                $statusRoll = fake()->numberBetween(1, 100);
                $status = ReportStatus::Unverified->value;
                $confirmationsCount = 0;

                if ($statusRoll > 60 && $statusRoll <= 88) {
                    $status = ReportStatus::PartiallyConfirmed->value;
                    $confirmationsCount = fake()->numberBetween(1, 2);
                } elseif ($statusRoll > 88) {
                    $status = ReportStatus::Confirmed->value;
                    $confirmationsCount = fake()->numberBetween(3, 8);
                }

                $rows[] = [
                    'title' => fake()->sentence(4),
                    'description' => fake()->paragraph(),
                    'latitude' => $this->clamp($hub['lat'] + fake()->randomFloat(6, -0.45, 0.45), -85, 85),
                    'longitude' => $this->clamp($hub['lng'] + fake()->randomFloat(6, -0.45, 0.45), -180, 180),
                    'category_id' => fake()->randomElement($categoryIds),
                    'reporter_ip_hash' => hash('sha256', fake()->ipv4()),
                    'reporter_browser_id' => (string) Str::uuid(),
                    'confirmations_count' => $confirmationsCount,
                    'clear_votes_count' => 0,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('reports')->insert($rows);
            $remaining -= $batchSize;
        }

        $this->seedSyntheticConfirmVotes($now);
    }

    private function seedSyntheticConfirmVotes(Carbon $timestamp): void
    {
        DB::table('reports')
            ->select(['id', 'confirmations_count'])
            ->where('confirmations_count', '>', 0)
            ->orderBy('id')
            ->chunkById(1000, function ($reports) use ($timestamp): void {
                $voteRows = [];

                foreach ($reports as $report) {
                    $confirmations = (int) $report->confirmations_count;

                    for ($counter = 0; $counter < $confirmations; $counter++) {
                        $voteRows[] = [
                            'report_id' => $report->id,
                            'vote_type' => 'confirm',
                            'ip_hash' => hash('sha256', fake()->ipv4()),
                            'browser_id' => (string) Str::uuid(),
                            'created_at' => $timestamp,
                        ];
                    }
                }

                if ($voteRows === []) {
                    return;
                }

                foreach (array_chunk($voteRows, 5000) as $chunk) {
                    DB::table('report_votes')->insert($chunk);
                }
            });
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return round(max($min, min($max, $value)), 7);
    }
}
