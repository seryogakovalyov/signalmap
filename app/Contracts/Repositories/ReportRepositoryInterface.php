<?php

namespace App\Contracts\Repositories;

use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    public function create(array $data): Report;

    public function activeWithinBounds(?array $bounds = null): Collection;

    public function pendingPaginated(int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator;

    public function publishedPaginated(int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator;

    public function updateStatus(Report $report, string $status): Report;

    public function syncVerificationState(Report $report, int $confirmationsCount, int $clearVotesCount, string $status): Report;

    public function delete(Report $report): void;
}
