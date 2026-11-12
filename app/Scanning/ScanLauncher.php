<?php

namespace App\Scanning;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScan;
use App\Models\Scan;
use App\Models\Site;

/**
 * Creates a scan for a site and hands it to the configured driver.
 */
class ScanLauncher
{
    /**
     * A scan stuck "running" longer than this is treated as dead.
     */
    public const STALE_AFTER_MINUTES = 15;

    public function launch(Site $site, ScanTrigger $trigger = ScanTrigger::Manual): Scan
    {
        $active = $site->activeScan();

        if ($active !== null) {
            if ($active->created_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
                return $active;
            }

            $active->update([
                'status' => ScanStatus::Failed,
                'error' => 'Scan timed out.',
                'finished_at' => now(),
            ]);
        }

        $scan = $site->scans()->create([
            'status' => ScanStatus::Queued,
            'trigger' => $trigger,
        ]);

        if (self::usesQueue()) {
            RunScan::dispatch($scan);
        }

        return $scan;
    }

    public static function usesQueue(): bool
    {
        return config('leakwatch.scan_driver') === 'queue';
    }
}
