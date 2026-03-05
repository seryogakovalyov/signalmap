<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicateReportVoteException;
use App\Exceptions\OwnReportConfirmationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportVoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class ReportVoteController extends Controller
{
    public function __construct(
        private readonly ReportVoteService $votes,
    ) {}

    public function confirm(Request $request, Report $report): JsonResponse
    {
        [$ipHash, $browserId, $cookie] = $this->resolveVoterFingerprint($request);

        try {
            $report = $this->votes->confirm($report, $ipHash, $browserId);
            $report->load('category');

            return $this->reportResponse($report, $cookie);
        } catch (OwnReportConfirmationException $exception) {
            return $this->errorResponse($exception->getMessage(), 403, $cookie);
        } catch (DuplicateReportVoteException $exception) {
            return $this->errorResponse($exception->getMessage(), 409, $cookie);
        }
    }

    public function clear(Request $request, Report $report): JsonResponse
    {
        [$ipHash, $browserId, $cookie] = $this->resolveVoterFingerprint($request);

        try {
            $report = $this->votes->clear($report, $ipHash, $browserId);
            $report->load('category');

            return $this->reportResponse($report, $cookie);
        } catch (DuplicateReportVoteException $exception) {
            return $this->errorResponse($exception->getMessage(), 409, $cookie);
        }
    }

    private function resolveVoterFingerprint(Request $request): array
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

    private function reportResponse(Report $report, ?Cookie $cookie): JsonResponse
    {
        $response = (new ReportResource($report))->response();

        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function errorResponse(string $message, int $statusCode, ?Cookie $cookie): JsonResponse
    {
        $response = response()->json([
            'message' => $message,
            'errors' => [
                'vote' => [$message],
            ],
        ], $statusCode);

        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
