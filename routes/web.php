<?php

use App\Enums\UserRole;
use App\Http\Controllers\Web\Admin\ReportModerationController;
use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\MapPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/map');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/map', MapPageController::class)->name('map.index');

Route::middleware(['auth', 'role:'.UserRole::Admin->value.','.UserRole::Moderator->value])
    ->prefix('admin/reports')
    ->name('admin.reports.')
    ->group(function (): void {
        Route::get('/', [ReportModerationController::class, 'index'])->name('index');
        Route::post('{report}/approve', [ReportModerationController::class, 'approve'])->name('approve');
        Route::post('{report}/reject', [ReportModerationController::class, 'reject'])->name('reject');
        Route::delete('{report}', [ReportModerationController::class, 'destroy'])->name('destroy');
    });
