<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReportModerationController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function index(): View
    {
        return view('admin.reports.index', [
            'pendingReports' => $this->reports->pendingForModeration(),
            'publishedReports' => $this->reports->publishedForManagement(),
        ]);
    }

    public function approve(Report $report): RedirectResponse
    {
        $this->reports->approve($report);

        return back()->with('status', 'Report approved.');
    }

    public function reject(Report $report): RedirectResponse
    {
        $this->reports->reject($report);

        return back()->with('status', 'Report rejected.');
    }

    public function destroy(Report $report): RedirectResponse
    {
        $this->reports->delete($report);

        return back()->with('status', 'Report deleted.');
    }
}
