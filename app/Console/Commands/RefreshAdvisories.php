<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeDependencySnapshot;
use App\Models\Site;
use App\Scanning\ScanLauncher;
use Illuminate\Console\Command;

class RefreshAdvisories extends Command
{
    protected $signature = 'leakwatch:refresh-advisories';

    protected $description = 'Re-check every site\'s latest package list against new advisories';

    public function handle(): int
    {
        Site::query()->with('latestSnapshot')->each(function (Site $site) {
            if ($site->latestSnapshot === null) {
                return;
            }

            ScanLauncher::usesQueue()
                ? AnalyzeDependencySnapshot::dispatch($site->latestSnapshot)
                : AnalyzeDependencySnapshot::dispatchSync($site->latestSnapshot);
        });

        return self::SUCCESS;
    }
}
