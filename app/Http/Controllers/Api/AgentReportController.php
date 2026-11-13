<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeDependencySnapshot;
use App\Models\DependencySnapshot;
use App\Models\Site;
use App\Scanning\ScanLauncher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receives the package list from the Surfacewatch agent installed in a Laravel app.
 */
class AgentReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        $validated = $request->validate([
            'lock_hash' => ['nullable', 'string', 'max:64'],
            'php_version' => ['nullable', 'string', 'max:32'],
            'laravel_version' => ['nullable', 'string', 'max:32'],
            'packages' => ['required', 'array', 'max:3000'],
            'packages.*.name' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/'],
            'packages.*.version' => ['required', 'string', 'max:100'],
            'packages.*.dev' => ['sometimes', 'boolean'],
        ]);

        $snapshot = DB::transaction(function () use ($site, $validated) {
            $snapshot = $site->dependencySnapshots()->create([
                'lock_hash' => $validated['lock_hash'] ?? null,
                'php_version' => $validated['php_version'] ?? null,
                'laravel_version' => $validated['laravel_version'] ?? null,
                'package_count' => count($validated['packages']),
                'reported_at' => now(),
            ]);

            $rows = array_map(fn (array $package) => [
                'dependency_snapshot_id' => $snapshot->id,
                'name' => strtolower($package['name']),
                'version' => $package['version'],
                'is_dev' => (bool) ($package['dev'] ?? false),
            ], $validated['packages']);

            foreach (array_chunk($rows, 500) as $chunk) {
                $snapshot->packages()->insert($chunk);
            }

            $site->forceFill(['agent_last_seen_at' => now()])->save();

            return $snapshot;
        });

        $this->analyze($snapshot);

        return response()->json([
            'status' => 'received',
            'snapshot_id' => $snapshot->id,
            'packages' => $snapshot->package_count,
        ], 202);
    }

    private function analyze(DependencySnapshot $snapshot): void
    {
        // With a queue worker (production) analyse in the background; otherwise
        // run it after the response is sent so the agent isn't kept waiting.
        if (ScanLauncher::usesQueue()) {
            AnalyzeDependencySnapshot::dispatch($snapshot);
        } else {
            AnalyzeDependencySnapshot::dispatchAfterResponse($snapshot);
        }
    }
}
