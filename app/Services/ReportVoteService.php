<?php

namespace App\Services;

use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Contracts\Repositories\ReportVoteRepositoryInterface;
use App\Enums\ReportStatus;
use App\Enums\ReportVoteType;
use App\Exceptions\DuplicateReportVoteException;
use App\Exceptions\OwnReportConfirmationException;
use App\Models\Report;

class ReportVoteService
{
    public function __construct(
        private readonly ReportVoteRepositoryInterface $votes,
        private readonly ReportRepositoryInterface $reports,
    ) {}

    public function confirm(Report $report, string $ipHash, string $browserId): Report
    {
        if ($report->belongsToReporter($ipHash, $browserId)) {
            throw new OwnReportConfirmationException('You cannot confirm your own report.');
        }

        return $this->registerVote($report, ReportVoteType::Confirm, $ipHash, $browserId);
    }

    public function clear(Report $report, string $ipHash, string $browserId): Report
    {
        return $this->registerVote($report, ReportVoteType::Clear, $ipHash, $browserId);
    }

    private function registerVote(Report $report, ReportVoteType $voteType, string $ipHash, string $browserId): Report
    {
        if ($this->votes->hasDuplicateVote($report, $voteType->value, $ipHash, $browserId)) {
            throw new DuplicateReportVoteException(sprintf('You have already submitted a %s vote for this report.', $voteType->value));
        }

        $this->votes->create([
            'report_id' => $report->id,
            'vote_type' => $voteType->value,
            'ip_hash' => $ipHash,
            'browser_id' => $browserId,
        ]);

        $confirmationsCount = $this->votes->countForReportByType($report, ReportVoteType::Confirm->value);
        $clearVotesCount = $this->votes->countForReportByType($report, ReportVoteType::Clear->value);

        return $this->reports->syncVerificationState(
            $report,
            $confirmationsCount,
            $clearVotesCount,
            $this->resolveStatus($confirmationsCount, $clearVotesCount)->value,
        );
    }

    private function resolveStatus(int $confirmationsCount, int $clearVotesCount): ReportStatus
    {
        if ($clearVotesCount >= 3) {
            return ReportStatus::Resolved;
        }

        if ($confirmationsCount >= 3) {
            return ReportStatus::Confirmed;
        }

        if ($confirmationsCount >= 1) {
            return ReportStatus::PartiallyConfirmed;
        }

        return ReportStatus::Unverified;
    }
}
