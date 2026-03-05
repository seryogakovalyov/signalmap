<?php

namespace App\Services;

use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportService
{
    public function __construct(
        private readonly ReportRepositoryInterface $reports,
    ) {}

    public function publishedForMap(?string $bbox): Collection
    {
        return $this->reports->activeWithinBounds(
            $bbox ? $this->parseBoundingBox($bbox) : null,
        );
    }

    public function createPending(array $payload, array $metadata = []): Report
    {
        return $this->reports->create([
            ...$payload,
            ...$metadata,
            'confirmations_count' => 0,
            'clear_votes_count' => 0,
            'status' => ReportStatus::Unverified->value,
        ]);
    }

    public function pendingForModeration(int $perPage = 20, string $pageName = 'pending_page'): LengthAwarePaginator
    {
        return $this->reports->pendingPaginated($perPage, $pageName);
    }

    public function publishedForManagement(int $perPage = 20, string $pageName = 'published_page'): LengthAwarePaginator
    {
        return $this->reports->publishedPaginated($perPage, $pageName);
    }

    public function approve(Report $report): Report
    {
        return $this->reports->syncVerificationState($report, max(3, $report->confirmations_count), $report->clear_votes_count, ReportStatus::Confirmed->value);
    }

    public function reject(Report $report): Report
    {
        return $this->reports->syncVerificationState($report, $report->confirmations_count, max(3, $report->clear_votes_count), ReportStatus::Resolved->value);
    }

    public function delete(Report $report): void
    {
        $this->reports->delete($report);
    }

    private function parseBoundingBox(string $bbox): array
    {
        $segments = array_map('trim', explode(',', $bbox));

        if (count($segments) !== 4) {
            throw new InvalidArgumentException('The bbox query parameter must contain four comma-separated coordinates.');
        }

        foreach ($segments as $segment) {
            if (! is_numeric($segment)) {
                throw new InvalidArgumentException('The bbox query parameter contains an invalid coordinate.');
            }
        }

        $coordinates = array_map(static fn (string $value): float => (float) $value, $segments);

        foreach ($coordinates as $coordinate) {
            if (! is_finite($coordinate)) {
                throw new InvalidArgumentException('The bbox query parameter contains an invalid coordinate.');
            }
        }

        [$lat1, $lng1, $lat2, $lng2] = $coordinates;

        return [
            'south' => min($lat1, $lat2),
            'west' => min($lng1, $lng2),
            'north' => max($lat1, $lat2),
            'east' => max($lng1, $lng2),
        ];
    }
}
