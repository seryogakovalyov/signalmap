<?php

namespace Tests\Feature\Api;

use App\Enums\ReportStatus;
use App\Models\Report;
use Tests\Feature\DatabaseTestCase;

class ReportMapApiTest extends DatabaseTestCase
{
    public function test_reports_endpoint_filters_by_bbox_and_hides_resolved_reports(): void
    {
        $visibleInside = Report::factory()->confirmed()->create([
            'latitude' => 50.4501,
            'longitude' => 30.5234,
        ]);

        Report::factory()->resolved()->create([
            'latitude' => 50.4502,
            'longitude' => 30.5235,
        ]);

        Report::factory()->create([
            'status' => ReportStatus::Unverified,
            'latitude' => 50.6000,
            'longitude' => 30.9000,
        ]);

        $response = $this->getJson('/api/reports?bbox=50.40,30.40,50.50,30.60');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleInside->id);
    }
}
