<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Enums\ReportVoteType;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportVote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoReportsSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TITLES_BY_CATEGORY = [
        'traffic' => [
            'Minor road accident',
            'Heavy traffic reported',
            'Traffic congestion near intersection',
        ],
        'infrastructure' => [
            'Road repair',
            'Broken street light',
            'Damaged sidewalk',
        ],
        'environment' => [
            'Illegal trash dumping',
            'Fallen tree',
            'Air pollution complaint',
        ],
        'community' => [
            'Neighborhood cleanup',
            'Local event',
            'Community assistance request',
        ],
        'safety' => [
            'Suspicious activity reported',
            'Police presence',
            'Unsafe area reported',
        ],
    ];

    /**
     * @var array<int, array{name: string, lat: float, lng: float}>
     */
    private const UKRAINE_CITY_CENTERS = [
        ['name' => 'Lviv', 'lat' => 49.8397, 'lng' => 24.0297],
        ['name' => 'Odesa', 'lat' => 46.4825, 'lng' => 30.7233],
        ['name' => 'Dnipro', 'lat' => 48.4647, 'lng' => 35.0462],
        ['name' => 'Zaporizhzhia', 'lat' => 47.8388, 'lng' => 35.1396],
        ['name' => 'Vinnytsia', 'lat' => 49.2331, 'lng' => 28.4682],
        ['name' => 'Poltava', 'lat' => 49.5883, 'lng' => 34.5514],
        ['name' => 'Mykolaiv', 'lat' => 46.9750, 'lng' => 31.9946],
        ['name' => 'Chernihiv', 'lat' => 51.4982, 'lng' => 31.2893],
        ['name' => 'Ivano-Frankivsk', 'lat' => 48.9226, 'lng' => 24.7111],
    ];

    public function run(): void
    {
        $categoryMap = Category::query()
            ->whereIn('name', ['Community', 'Environment', 'Infrastructure', 'Safety', 'Traffic'])
            ->get()
            ->mapWithKeys(fn (Category $category): array => [strtolower($category->name) => $category])
            ->all();

        if ($categoryMap === []) {
            return;
        }

        ReportVote::query()->delete();
        Report::query()->delete();

        $plannedLocations = [
            ...$this->buildCluster(50.4501, 30.5234, 10), // Kyiv
            ...$this->buildCluster(49.9935, 36.2304, 8),  // Kharkiv
            ...$this->buildRandomUkraineCitiesCluster(random_int(count(self::UKRAINE_CITY_CENTERS), 10)),
        ];

        $categoryKeys = array_keys($categoryMap);
        shuffle($plannedLocations);

        foreach ($plannedLocations as $location) {
            $categoryKey = $categoryKeys[array_rand($categoryKeys)];
            $statusData = $this->randomStatusData();
            $title = self::TITLES_BY_CATEGORY[$categoryKey][array_rand(self::TITLES_BY_CATEGORY[$categoryKey])];

            $report = Report::query()->create([
                'title' => $title,
                'description' => sprintf(
                    '%s observed near %.4f, %.4f. Community verification requested.',
                    $title,
                    $location['lat'],
                    $location['lng']
                ),
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
                'category_id' => $categoryMap[$categoryKey]->id,
                'reporter_ip_hash' => hash('sha256', fake()->ipv4()),
                'reporter_browser_id' => (string) Str::uuid(),
                'confirmations_count' => $statusData['confirmations_count'],
                'clear_votes_count' => 0,
                'status' => $statusData['status'],
            ]);

            $this->seedConfirmVotes($report->id, $statusData['confirmations_count']);
        }
    }

    /**
     * @return array{status: string, confirmations_count: int}
     */
    private function randomStatusData(): array
    {
        $roll = random_int(1, 100);

        if ($roll <= 45) {
            return [
                'status' => ReportStatus::Unverified->value,
                'confirmations_count' => 0,
            ];
        }

        if ($roll <= 80) {
            return [
                'status' => ReportStatus::PartiallyConfirmed->value,
                'confirmations_count' => random_int(1, 2),
            ];
        }

        return [
            'status' => ReportStatus::Confirmed->value,
            'confirmations_count' => random_int(3, 6),
        ];
    }

    private function seedConfirmVotes(int $reportId, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            ReportVote::query()->create([
                'report_id' => $reportId,
                'vote_type' => ReportVoteType::Confirm->value,
                'ip_hash' => hash('sha256', fake()->ipv4()),
                'browser_id' => (string) Str::uuid(),
            ]);
        }
    }

    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    private function buildCluster(float $centerLat, float $centerLng, int $count): array
    {
        $points = [];

        for ($index = 0; $index < $count; $index++) {
            $points[] = [
                'lat' => round($centerLat + $this->signedOffset(), 7),
                'lng' => round($centerLng + $this->signedOffset(), 7),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{lat: float, lng: float}>
     */
    private function buildRandomUkraineCitiesCluster(int $count): array
    {
        $points = [];

        $cities = self::UKRAINE_CITY_CENTERS;
        $count = max($count, count($cities));

        // Ensure each listed city has at least one report.
        foreach ($cities as $city) {
            $points[] = [
                'lat' => round($city['lat'] + $this->signedOffset(), 7),
                'lng' => round($city['lng'] + $this->signedOffset(), 7),
            ];
        }

        $extraPointsCount = $count - count($cities);

        for ($index = 0; $index < $extraPointsCount; $index++) {
            $city = $cities[array_rand($cities)];
            $points[] = [
                'lat' => round($city['lat'] + $this->signedOffset(), 7),
                'lng' => round($city['lng'] + $this->signedOffset(), 7),
            ];
        }

        return $points;
    }

    private function signedOffset(): float
    {
        $offset = random_int(1000, 3000) / 100000;
        return random_int(0, 1) === 1 ? $offset : -$offset;
    }
}
