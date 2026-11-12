<?php

namespace App\Providers;

use App\Scanning\Contracts\DnsResolver;
use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Net\StreamTlsInspector;
use App\Scanning\Net\SystemDnsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Network lookups sit behind interfaces so tests can swap in fakes.
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(TlsInspector::class, StreamTlsInspector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
