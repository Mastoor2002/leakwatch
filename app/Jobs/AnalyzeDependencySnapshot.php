<?php

namespace App\Jobs;

use App\Dependencies\AdvisoryClient;
use App\Dependencies\VersionConstraint;
use App\Enums\FindingStatus;
use App\Models\DependencySnapshot;
use App\Models\InstalledPackage;
use App\Models\PackageFinding;
use App\Notifications\NewAdvisoriesFound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Compares a site's installed packages with known advisories and keeps
 * package_findings in sync (new -> open, fixed by an upgrade -> resolved).
 */
class AnalyzeDependencySnapshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public DependencySnapshot $snapshot)
    {
        //
    }

    public function handle(AdvisoryClient $client): void
    {
        $snapshot = $this->snapshot;
        $site = $snapshot->site;
        $packages = $snapshot->packages()->get()->keyBy('name');

        $advisories = $client->forPackages($packages->keys()->all());

        $newFindings = DB::transaction(function () use ($snapshot, $site, $packages, $advisories) {
            $matchedAdvisoryIds = [];
            $new = collect();

            foreach ($advisories as $packageName => $packageAdvisories) {
                /** @var InstalledPackage|null $installed */
                $installed = $packages->get($packageName);

                if ($installed === null) {
                    continue;
                }

                foreach ($packageAdvisories as $advisory) {
                    if (! VersionConstraint::matches($installed->version, $advisory->affected_versions)) {
                        continue;
                    }

                    $matchedAdvisoryIds[] = $advisory->id;

                    $finding = PackageFinding::firstOrNew(['site_id' => $site->id, 'advisory_id' => $advisory->id]);
                    $isNew = ! $finding->exists || $finding->status === FindingStatus::Resolved;

                    $finding->fill([
                        'package_name' => $packageName,
                        'installed_version' => $installed->version,
                        'is_dev' => $installed->is_dev,
                    ]);

                    if ($isNew) {
                        $finding->fill([
                            'status' => FindingStatus::Open,
                            'first_seen_at' => now(),
                            'resolved_at' => null,
                            'notified_at' => null,
                        ]);
                        $new->push($finding);
                    }

                    $finding->save();
                }
            }

            PackageFinding::where('site_id', $site->id)
                ->where('status', FindingStatus::Open)
                ->whereNotIn('advisory_id', $matchedAdvisoryIds)
                ->update(['status' => FindingStatus::Resolved, 'resolved_at' => now()]);

            $snapshot->forceFill(['analyzed_at' => now()])->save();

            return $new;
        });

        $production = $newFindings->reject(fn (PackageFinding $finding) => $finding->is_dev);

        if ($production->isNotEmpty() && $site->team?->alerts_enabled) {
            NewAdvisoriesFound::send($site, $production);
        }
    }
}
