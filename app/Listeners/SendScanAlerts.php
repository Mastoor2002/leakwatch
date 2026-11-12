<?php

namespace App\Listeners;

use App\Enums\FindingStatus;
use App\Enums\Severity;
use App\Events\ScanCompleted;
use App\Models\Finding;
use App\Notifications\NewFindingsFound;

/**
 * After a scan, alert the team about new critical/high findings - once each.
 */
class SendScanAlerts
{
    public function handle(ScanCompleted $event): void
    {
        $site = $event->scan->site;

        $findings = Finding::where('site_id', $site->id)
            ->where('status', FindingStatus::Open)
            ->whereNull('notified_at')
            ->whereIn('severity', [Severity::Critical->value, Severity::High->value])
            ->get();

        if ($findings->isEmpty()) {
            return;
        }

        NewFindingsFound::send($site, $findings);

        Finding::whereIn('id', $findings->pluck('id'))->update(['notified_at' => now()]);
    }
}
