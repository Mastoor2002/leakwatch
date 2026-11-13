<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScanResource;
use App\Jobs\RunScan;
use App\Models\Site;
use App\Models\Team;
use App\Scanning\ScanLauncher;
use App\Scanning\ScanRunner;
use App\Scanning\SiteVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Scan and verification actions for the mobile app.
 *
 * With the inline driver there is no queue worker, so the scan is finished
 * after the response is sent and every status poll nudges it along as a
 * fallback - the same trick the Livewire page uses with wire:poll.
 */
class SiteScanController extends Controller
{
    public function store(Request $request, Team $team, Site $site, ScanLauncher $launcher): JsonResponse
    {
        $this->authorizeSite($request, $team, $site);
        Gate::authorize('scan', $site);

        $scan = $launcher->launch($site);

        if (! ScanLauncher::usesQueue() && $scan->wasRecentlyCreated) {
            RunScan::dispatchAfterResponse($scan);
        }

        return response()->json(['scan' => new ScanResource($scan)], 202);
    }

    public function status(Request $request, Team $team, Site $site, ScanRunner $runner): JsonResponse
    {
        $this->authorizeSite($request, $team, $site);
        Gate::authorize('view', $site);

        $scan = $site->activeScan();

        if ($scan !== null && ! ScanLauncher::usesQueue()) {
            $runner->step($scan);
            $scan->refresh();
        }

        $scan ??= $site->scans()->latest('id')->first();

        $scan?->load('checkRuns');
        $site->refresh();

        return response()->json([
            'scan' => $scan ? new ScanResource($scan) : null,
            'site' => [
                'score' => $site->score,
                'grade' => $site->grade,
                'last_scanned_at' => $site->last_scanned_at?->toIso8601String(),
                'open_findings' => $site->openFindings()->count(),
            ],
        ]);
    }

    public function verify(Request $request, Team $team, Site $site, SiteVerifier $verifier): JsonResponse
    {
        $this->authorizeSite($request, $team, $site);
        Gate::authorize('update', $site);

        $method = $verifier->verify($site);
        $site->refresh();

        return response()->json([
            'verified' => $site->isVerified(),
            'method' => $method?->value,
            'message' => $site->isVerified()
                ? 'Ownership verified. Path probes are now enabled for this site.'
                : 'Verification record not found yet. DNS changes can take a few minutes.',
        ]);
    }

    private function authorizeSite(Request $request, Team $team, Site $site): void
    {
        abort_unless($request->user()?->belongsToTeam($team), 403, 'You are not a member of this team.');
        abort_unless((int) $site->team_id === (int) $team->id, 404);
    }
}
