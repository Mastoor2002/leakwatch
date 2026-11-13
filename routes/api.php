<?php

use App\Http\Controllers\Api\AgentReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SiteScanController;
use App\Http\Controllers\Api\TeamSitesController;
use App\Http\Middleware\AuthenticateAgent;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent API - called by the leakwatch/agent Composer package on each site
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware(['throttle:30,1', AuthenticateAgent::class])
    ->group(function () {
        Route::post('agent/report', AgentReportController::class)->name('api.agent.report');
    });

/*
|--------------------------------------------------------------------------
| Mobile / client API - personal access tokens (Authorization: Bearer lwu_…)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('auth/token', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.token');

    Route::middleware(['throttle:120,1', AuthenticateApiToken::class])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::delete('auth/token', [AuthController::class, 'logout'])->name('auth.revoke');

        Route::prefix('teams/{team}')->name('teams.')->group(function () {
            Route::get('overview', [TeamSitesController::class, 'overview'])->name('overview');
            Route::get('sites', [TeamSitesController::class, 'index'])->name('sites.index');
            Route::get('sites/{site}', [TeamSitesController::class, 'show'])->name('sites.show');
            Route::post('sites/{site}/scan', [SiteScanController::class, 'store'])->name('sites.scan');
            Route::get('sites/{site}/scan', [SiteScanController::class, 'status'])->name('sites.scan.status');
            Route::post('sites/{site}/verify', [SiteScanController::class, 'verify'])->name('sites.verify');
        });
    });
});
