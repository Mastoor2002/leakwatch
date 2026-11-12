<?php

namespace App\Scanning;

use App\Enums\CheckStatus;
use App\Enums\FindingStatus;
use App\Enums\ScanStatus;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\ScanCheckRun;
use App\Scanning\Data\CheckResult;
use Illuminate\Support\Facades\DB;

/**
 * Turns the raw results of a finished scan into Finding rows:
 *  - a problem seen for the first time -> new open finding,
 *  - seen again -> same row, last_seen updated (no duplicates),
 *  - previously open but not found this time -> resolved,
 *  - previously resolved but back again -> reopened (a regression).
 * Ignored findings stay ignored.
 */
class ScanReconciler
{
    public function reconcile(Scan $scan): void
    {
        DB::transaction(function () use ($scan) {
            $site = $scan->site;
            $runs = $scan->checkRuns()->get();

            $seen = [];
            $new = 0;
            $checksThatRan = [];

            foreach ($runs as $run) {
                /** @var ScanCheckRun $run */
                if ($run->status?->didRun()) {
                    $checksThatRan[] = $run->check_key;
                }

                if ($run->status !== CheckStatus::Failed || ! is_array($run->result)) {
                    continue;
                }

                $remediation = class_exists($run->check_class) ? $run->check_class::remediation() : '';

                foreach (CheckResult::fromArray($run->result)->findings as $data) {
                    $fingerprint = $data->fingerprint($run->check_key);
                    $seen[] = $fingerprint;

                    $finding = Finding::where('site_id', $site->id)->where('fingerprint', $fingerprint)->first();

                    $attributes = [
                        'severity' => $data->severity,
                        'title' => $data->title,
                        'summary' => $data->summary,
                        'evidence' => $data->evidence,
                        'remediation' => $remediation,
                        'last_seen_scan_id' => $scan->id,
                    ];

                    if ($finding === null) {
                        Finding::create($attributes + [
                            'site_id' => $site->id,
                            'check_key' => $run->check_key,
                            'fingerprint' => $fingerprint,
                            'status' => FindingStatus::Open,
                            'first_seen_scan_id' => $scan->id,
                        ]);
                        $new++;

                        continue;
                    }

                    if ($finding->status === FindingStatus::Resolved) {
                        $attributes += ['status' => FindingStatus::Open, 'resolved_at' => null, 'notified_at' => null];
                        $new++;
                    }

                    $finding->update($attributes);
                }
            }

            $resolved = Finding::where('site_id', $site->id)
                ->where('status', FindingStatus::Open)
                ->whereIn('check_key', $checksThatRan)
                ->whereNotIn('fingerprint', $seen)
                ->update(['status' => FindingStatus::Resolved, 'resolved_at' => now()]);

            $result = Scoring::calculate(
                $site->openFindings()->get()->map(fn (Finding $finding) => $finding->severity),
            );

            $scan->update([
                'status' => ScanStatus::Completed,
                'finished_at' => now(),
                'score' => $result['score'],
                'grade' => $result['grade'],
                'new_findings_count' => $new,
                'resolved_findings_count' => $resolved,
            ]);

            $site->forceFill([
                'score' => $result['score'],
                'grade' => $result['grade'],
                'last_scan_id' => $scan->id,
                'last_scanned_at' => now(),
            ])->save();
        });
    }
}
