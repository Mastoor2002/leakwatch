<?php

namespace App\Http\Controllers;

use App\Enums\FindingStatus;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A clean, printable security report for one site. Use the browser's
 * "Save as PDF" to send it to a client.
 */
class SiteReportController extends Controller
{
    public function __invoke(Request $request, string $currentTeam, Site $site): View
    {
        Gate::authorize('view', $site);
        abort_unless((int) $site->team_id === (int) $request->user()->current_team_id, 404);

        $site->load(['client', 'team', 'lastScan', 'latestSnapshot']);

        $findings = $site->findings()
            ->where('status', FindingStatus::Open)
            ->get()
            ->sortByDesc(fn (Finding $finding) => $finding->severity->rank());

        $resolved = $site->findings()
            ->where('status', FindingStatus::Resolved)
            ->where('resolved_at', '>=', now()->subDays(30))
            ->latest('resolved_at')
            ->get();

        $packages = $site->packageFindings()
            ->with('advisory')
            ->where('status', FindingStatus::Open)
            ->get();

        return view('reports.site', [
            'site' => $site,
            'findings' => $findings,
            'resolved' => $resolved,
            'packages' => $packages,
            'counts' => collect(Severity::ordered())->mapWithKeys(fn (Severity $severity) => [
                $severity->value => $findings->filter(fn (Finding $finding) => $finding->severity === $severity)->count(),
            ]),
        ]);
    }
}
