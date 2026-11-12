<?php

namespace Leakwatch\Agent;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Leakwatch\Agent\Console\ReportCommand;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/leakwatch-agent.php', 'leakwatch-agent');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ReportCommand::class]);

        $this->publishes([
            __DIR__.'/../config/leakwatch-agent.php' => config_path('leakwatch-agent.php'),
        ], 'leakwatch-agent-config');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('leakwatch-agent.schedule')) {
                $schedule->command('leakwatch:report')->dailyAt('03:17')->withoutOverlapping();
            }
        });
    }
}
