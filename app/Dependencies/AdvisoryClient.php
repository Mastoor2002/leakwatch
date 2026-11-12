<?php

namespace App\Dependencies;

use App\Models\Advisory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches known vulnerabilities for Composer packages from Packagist's public
 * security advisories API (the same data `composer audit` uses) and caches
 * them in the advisories table.
 */
class AdvisoryClient
{
    /**
     * @param  array<int, string>  $packageNames
     * @return array<string, array<int, Advisory>> package name => advisories
     */
    public function forPackages(array $packageNames): array
    {
        $packageNames = array_values(array_unique(array_map('strtolower', $packageNames)));
        $result = [];

        foreach (array_chunk($packageNames, (int) config('leakwatch.advisories.batch_size', 50)) as $batch) {
            sort($batch);
            $cacheKey = 'leakwatch:advisories:'.sha1(implode(',', $batch));

            $ids = Cache::remember($cacheKey, now()->addMinutes((int) config('leakwatch.advisories.cache_minutes', 360)), function () use ($batch) {
                return $this->fetchAndStore($batch);
            });

            foreach (Advisory::whereIn('id', $ids)->get() as $advisory) {
                $result[$advisory->package_name][] = $advisory;
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $batch
     * @return array<int, int> advisory row ids
     */
    private function fetchAndStore(array $batch): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->withHeaders(['User-Agent' => (string) config('leakwatch.http.user_agent')])
            ->post((string) config('leakwatch.advisories.endpoint'), ['packages' => $batch]);

        $response->throw();

        $ids = [];

        foreach ((array) $response->json('advisories', []) as $packageName => $advisories) {
            foreach ((array) $advisories as $data) {
                if (! isset($data['advisoryId'], $data['affectedVersions'])) {
                    continue;
                }

                $advisory = Advisory::updateOrCreate(
                    ['advisory_id' => (string) $data['advisoryId']],
                    [
                        'package_name' => strtolower((string) ($data['packageName'] ?? $packageName)),
                        'title' => mb_substr((string) ($data['title'] ?? 'Security advisory'), 0, 250),
                        'cve' => $data['cve'] ?? null,
                        'link' => isset($data['link']) ? mb_substr((string) $data['link'], 0, 250) : null,
                        'affected_versions' => (string) $data['affectedVersions'],
                        'severity' => $data['severity'] ?? null,
                        'reported_at' => $data['reportedAt'] ?? null,
                    ],
                );

                $ids[] = $advisory->id;
            }
        }

        return $ids;
    }
}
