<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function index(IndexReportRequest $request): JsonResponse
    {
        try {
            $reports = $this->reports->publishedForMap($request->validated('bbox'));
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'bbox' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return ReportResource::collection($reports)->response();
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        [$ipHash, $browserId, $cookie] = $this->resolveReporterFingerprint($request);

        $report = $this->reports->createPending($request->validated(), [
            'reporter_ip_hash' => $ipHash,
            'reporter_browser_id' => $browserId,
        ]);
        $report->load('category');

        $response = (new ReportResource($report))
            ->response()
            ->setStatusCode(201);

        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function resolveReporterFingerprint(Request $request): array
    {
        $browserId = $request->cookie('browser_id');
        $shouldSetCookie = false;

        if (! is_string($browserId) || $browserId === '') {
            $browserId = (string) Str::uuid();
            $shouldSetCookie = true;
        }

        $cookie = $shouldSetCookie
            ? cookie(
                'browser_id',
                $browserId,
                60 * 24 * 365 * 5,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            )
            : null;

        return [
            hash('sha256', (string) $request->ip()),
            $browserId,
            $cookie,
        ];
    }
}
