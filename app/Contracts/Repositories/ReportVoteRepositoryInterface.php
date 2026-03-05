<?php

namespace App\Contracts\Repositories;

use App\Models\Report;
use App\Models\ReportVote;

interface ReportVoteRepositoryInterface
{
    public function create(array $data): ReportVote;

    public function countForReportByType(Report $report, string $voteType): int;

    public function hasDuplicateVote(Report $report, string $voteType, string $ipHash, string $browserId): bool;
}
