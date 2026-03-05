<?php

namespace Tests\Feature\Api;

use App\Enums\ReportStatus;
use App\Models\Report;
use Tests\Feature\DatabaseTestCase;

class ReportVotingTest extends DatabaseTestCase
{
    public function test_confirm_vote_updates_counters_and_status(): void
    {
        $report = Report::factory()->create([
            'reporter_ip_hash' => hash('sha256', '203.0.113.10'),
            'reporter_browser_id' => 'owner-browser',
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withCookie('browser_id', 'browser-a')
            ->postJson("/api/reports/{$report->id}/confirm");

        $response
            ->assertOk()
            ->assertJsonPath('data.confirmations_count', 1)
            ->assertJsonPath('data.status', ReportStatus::PartiallyConfirmed->value);
    }

    public function test_duplicate_confirm_vote_is_blocked_for_same_browser_or_ip(): void
    {
        $report = Report::factory()->create();

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
            ->withCookie('browser_id', 'browser-b')
            ->postJson("/api/reports/{$report->id}/confirm")
            ->assertOk();

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
            ->withCookie('browser_id', 'browser-c')
            ->postJson("/api/reports/{$report->id}/confirm")
            ->assertStatus(409);
    }

    public function test_same_user_can_clear_after_confirming(): void
    {
        $report = Report::factory()->create();

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->withCookie('browser_id', 'browser-d')
            ->postJson("/api/reports/{$report->id}/confirm")
            ->assertOk();

        $this
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->withCookie('browser_id', 'browser-d')
            ->postJson("/api/reports/{$report->id}/clear")
            ->assertOk()
            ->assertJsonPath('data.clear_votes_count', 1);
    }

    public function test_report_owner_cannot_confirm_own_report(): void
    {
        $ownerIp = '198.51.100.23';
        $ownerBrowser = 'owner-browser-e';

        $report = Report::factory()->create([
            'reporter_ip_hash' => hash('sha256', $ownerIp),
            'reporter_browser_id' => $ownerBrowser,
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => $ownerIp])
            ->withCookie('browser_id', $ownerBrowser)
            ->postJson("/api/reports/{$report->id}/confirm")
            ->assertStatus(403);
    }
}
