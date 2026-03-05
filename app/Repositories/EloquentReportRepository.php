<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentReportRepository implements ReportRepositoryInterface
{
    public function create(array $data): Report
    {
        return Report::query()->create($data);
    }

    public function activeWithinBounds(?array $bounds = null): Collection
    {
        return Report::query()
            ->with('category')
            ->visibleOnMap()
            ->when($bounds, function ($query, array $bbox): void {
                $query
                    ->whereBetween('latitude', [$bbox['south'], $bbox['north']])
                    ->whereBetween('longitude', [$bbox['west'], $bbox['east']]);
            })
            ->latest()
            ->get();
    }

    public function pendingPaginated(int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        return Report::query()
            ->with('category')
            ->where('status', ReportStatus::Unverified->value)
            ->latest()
            ->paginate($perPage, ['*'], $pageName);
    }

    public function publishedPaginated(int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        return Report::query()
            ->with('category')
            ->whereIn('status', [
                ReportStatus::PartiallyConfirmed->value,
                ReportStatus::Confirmed->value,
            ])
            ->latest()
            ->paginate($perPage, ['*'], $pageName);
    }

    public function updateStatus(Report $report, string $status): Report
    {
        $report->update([
            'status' => $status,
        ]);

        return $report->refresh();
    }

    public function syncVerificationState(Report $report, int $confirmationsCount, int $clearVotesCount, string $status): Report
    {
        $report->update([
            'confirmations_count' => $confirmationsCount,
            'clear_votes_count' => $clearVotesCount,
            'status' => $status,
        ]);

        return $report->refresh();
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }
}
