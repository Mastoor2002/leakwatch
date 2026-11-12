<?php

use App\Http\Controllers\Api\AgentReportController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['throttle:30,1', AuthenticateAgent::class])
    ->group(function () {
        Route::post('agent/report', AgentReportController::class)->name('api.agent.report');
    });
