<?php

namespace App\Console\Commands;

use App\Enums\ScanFrequency;
use App\Enums\ScanTrigger;
use App\Models\Site;
use App\Scanning\ScanLauncher;
use App\Scanning\ScanRunner;
use Illuminate\Console\Command;

class ScanDueSites extends Command
{
    protected $signature = 'leakwatch:scan-due';

    protected $description = 'Start scheduled scans for sites that are due';

    public function handle(ScanLauncher $launcher, ScanRunner $runner): int
    {
        $started = 0;

        Site::query()
            ->whereNotNull('verified_at')
            ->where('scan_frequency', '!=', ScanFrequency::Off->value)
            ->each(function (Site $site) use ($launcher, $runner, &$started) {
                $interval = $site->scan_frequency->interval();

                if ($interval === null || ($site->last_scanned_at && $site->last_scanned_at->add($interval)->isFuture())) {
                    return;
                }

                $scan = $launcher->launch($site, ScanTrigger::Scheduled);

                // Without a queue worker, run the scan right here in the scheduler.
                if (! ScanLauncher::usesQueue()) {
                    $runner->runToCompletion($scan);
                }

                $started++;
                $this->line("Scanned {$site->host}");
            });

        $this->info("{$started} scheduled scan(s) started.");

        return self::SUCCESS;
    }
}
