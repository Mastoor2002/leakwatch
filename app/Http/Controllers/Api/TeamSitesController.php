<?php

namespace App\Http\Controllers\Api;

use App\Enums\FindingStatus;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Http\Resources\SiteResource;
use App\Models\Finding;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Read endpoints the mobile app uses: a team overview, the site list and a
 * single site with its findings.
 */
class TeamSitesController extends Controller
{
    public function overview(Request $request, Team $team): JsonResponse
    {
        $this->authorizeMember($request, $team);

        $sites = $team->sites()->with('openFindings')->get();
        $openFindings = $sites->flatMap->openFindings;

        $bySeverity = [];

        foreach (Severity::ordered() as $severity) {
            $bySeverity[$severity->value] = $openFindings->where('severity', $severity)->count();
        }

        $grades = $sites->whereNotNull('grade')->countBy('grade');

        return response()->json([
            'team' => ['id' => $team->id, 'slug' => $team->slug, 'name' => $team->name],
            'stats' => [
                'sites' => $sites->count(),
                'verified_sites' => $sites->filter->isVerified()->count(),
                'scanned_sites' => $sites->whereNotNull('last_scanned_at')->count(),
                'open_findings' => $openFindings->count(),
                'open_by_severity' => $bySeverity,
                'grades' => collect(['A', 'B', 'C', 'D', 'F'])->mapWithKeys(fn ($g) => [$g => (int) ($grades[$g] ?? 0)]),
                'average_score' => $sites->whereNotNull('score')->avg('score') !== null
                    ? (int) round($sites->whereNotNull('score')->avg('score'))
                    : null,
            ],
            'needs_attention' => SiteResource::collection(
                $sites->filter(fn (Site $site) => $site->grade === null || in_array($site->grade, ['D', 'F'], true) || ! $site->isVerified())
                    ->sortBy(fn (Site $site) => $site->score ?? -1)
                    ->take(6)
                    ->values()
                    ->each->load('client')
            ),
        ]);
    }

    public function index(Request $request, Team $team): JsonResponse
    {
        $this->authorizeMember($request, $team);

        $sites = $team->sites()
            ->with(['client', 'openFindings'])
            ->orderByRaw('CASE WHEN score IS NULL THEN 1 ELSE 0 END, score ASC, host ASC')
            ->get();

        return response()->json(['sites' => SiteResource::collection($sites)]);
    }

    public function show(Request $request, Team $team, Site $site): JsonResponse
    {
        $this->authorizeMember($request, $team);
        $this->ensureSiteBelongsToTeam($site, $team);
        Gate::authorize('view', $site);

        $site->load([
            'client',
            'openFindings',
            'lastScan.checkRuns',
            'packageFindings' => fn ($q) => $q->where('status', FindingStatus::Open)->with('advisory'),
        ]);

        $site->setRelation('findings', $site->findings()
            ->orderByDesc('created_at')
            ->get()
            ->sortBy([
                fn (Finding $a, Finding $b) => ($a->status === FindingStatus::Open ? 0 : 1) <=> ($b->status === FindingStatus::Open ? 0 : 1),
                fn (Finding $a, Finding $b) => $b->severity->rank() <=> $a->severity->rank(),
            ])
            ->values());

        return response()->json(['site' => new SiteResource($site)]);
    }

    private function authorizeMember(Request $request, Team $team): void
    {
        abort_unless($request->user()?->belongsToTeam($team), 403, 'You are not a member of this team.');
    }

    private function ensureSiteBelongsToTeam(Site $site, Team $team): void
    {
        abort_unless((int) $site->team_id === (int) $team->id, 404);
    }
}
