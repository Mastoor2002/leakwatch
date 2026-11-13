<?php

use App\Http\Controllers\SiteReportController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public page explaining what SurfacewatchBot is (our scanner's User-Agent links here).
Route::view('bot', 'bot')->name('bot');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');

        Route::livewire('clients', 'pages::clients.index')->name('clients.index');
        Route::livewire('sites', 'pages::sites.index')->name('sites.index');
        Route::livewire('sites/{site}', 'pages::sites.show')->name('sites.show');
        Route::get('sites/{site}/report', SiteReportController::class)->name('sites.report');
        Route::livewire('advisories', 'pages::advisories.index')->name('advisories.index');
        Route::livewire('alerts', 'pages::alerts.edit')->name('alerts.edit');
    });

require __DIR__.'/settings.php';
