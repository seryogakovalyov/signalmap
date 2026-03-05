<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReportVoteRepositoryInterface;
use App\Models\Report;
use App\Models\ReportVote;

class EloquentReportVoteRepository implements ReportVoteRepositoryInterface
{
    public function create(array $data): ReportVote
    {
        return ReportVote::query()->create($data);
    }

    public function countForReportByType(Report $report, string $voteType): int
    {
        return $report->votes()
            ->where('vote_type', $voteType)
            ->count();
    }

    public function hasDuplicateVote(Report $report, string $voteType, string $ipHash, string $browserId): bool
    {
        return $report->votes()
            ->where('vote_type', $voteType)
            ->where(function ($query) use ($ipHash, $browserId): void {
                $query
                    ->where('ip_hash', $ipHash)
                    ->orWhere('browser_id', $browserId);
            })
            ->exists();
    }
}
