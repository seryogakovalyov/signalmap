<?php

use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportVoteController;
use Illuminate\Support\Facades\Route;

Route::get('/reports', [ReportController::class, 'index'])->name('api.reports.index');
Route::post('/reports', [ReportController::class, 'store'])
    ->middleware('throttle:report-submissions')
    ->name('api.reports.store');
Route::post('/reports/{report}/confirm', [ReportVoteController::class, 'confirm'])
    ->middleware('throttle:report-votes')
    ->name('api.reports.confirm');
Route::post('/reports/{report}/clear', [ReportVoteController::class, 'clear'])
    ->middleware('throttle:report-votes')
    ->name('api.reports.clear');
