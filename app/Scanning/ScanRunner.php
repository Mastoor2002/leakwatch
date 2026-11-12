<?php

namespace App\Scanning;

use App\Enums\CheckStatus;
use App\Enums\ScanStatus;
use App\Events\ScanCompleted;
use App\Models\Scan;
use App\Models\ScanCheckRun;
use App\Scanning\Contracts\Check;
use App\Scanning\Data\CheckResult;
use App\Scanning\Data\PageSnapshot;
use App\Scanning\Exceptions\BlockedTargetException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a scan in small steps: start -> one check at a time -> finish.
 *
 * The queue driver calls runToCompletion() in a job. The inline driver calls
 * step() once per Livewire poll, so scans work on a laptop with no worker.
 */
class ScanRunner
{
    public function __construct(
        private SafeHttp $http,
        private ScanReconciler $reconciler,
    ) {
        //
    }

    /**
     * Do one unit of work. A cache lock makes sure two overlapping poll
     * requests never run the same step twice.
     */
    public function step(Scan $scan): void
    {
        Cache::lock("leakwatch:scan:{$scan->id}", 120)->get(function () use ($scan) {
            $scan->refresh();

            if ($scan->status === ScanStatus::Queued) {
                $this->start($scan);
            } elseif ($scan->status === ScanStatus::Running && ! $this->runNextCheck($scan)) {
                $this->finish($scan);
            }
        });
    }

    public function runToCompletion(Scan $scan): void
    {
        if ($scan->status === ScanStatus::Queued) {
            $this->start($scan);
        }

        while ($scan->refresh()->status === ScanStatus::Running && $this->runNextCheck($scan)) {
            //
        }

        if ($scan->status === ScanStatus::Running) {
            $this->finish($scan);
        }
    }

    public function start(Scan $scan): void
    {
        $scan->update(['status' => ScanStatus::Running, 'started_at' => now(), 'error' => null]);
        $site = $scan->site;

        try {
            [$response, $finalUrl] = $this->http->getFollowingRedirects(rtrim($site->url, '/').'/');
        } catch (BlockedTargetException $e) {
            $this->fail($scan, $e->getMessage());

            return;
        } catch (Throwable $e) {
            $this->fail($scan, "Could not reach {$site->url}: ".Str::limit($e->getMessage(), 200));

            return;
        }

        $origin = $this->origin($finalUrl);
        $baseline = null;

        try {
            $missing = $origin.'/leakwatch-'.Str::lower(Str::random(12)).'-not-found';
            $baseline = PageSnapshot::fromResponse($this->http->get($missing), $missing);
        } catch (Throwable) {
            // A missing baseline only makes checks a little more conservative.
        }

        $scan->update([
            'context' => [
                'origin' => $origin,
                'homepage' => PageSnapshot::fromResponse($response, $finalUrl)->toArray(),
                'baseline' => $baseline?->toArray(),
            ],
        ]);

        foreach (array_values(config('leakwatch.checks', [])) as $position => $class) {
            /** @var Check $check */
            $check = app($class);

            $scan->checkRuns()->create([
                'check_key' => $check->key(),
                'check_class' => $class,
                'position' => $position,
            ]);
        }
    }

    /**
     * Run the next pending check. Returns false when none are left.
     */
    public function runNextCheck(Scan $scan): bool
    {
        /** @var ScanCheckRun|null $run */
        $run = $scan->checkRuns()->whereNull('status')->orderBy('position')->first();

        if ($run === null) {
            return false;
        }

        $started = hrtime(true);

        try {
            /** @var Check $check */
            $check = app($run->check_class);
            $this->http->resetBudget();
            $context = ScanContext::fromScan($scan);

            if ($check->requiresVerification() && ! $scan->site->isVerified()) {
                $result = CheckResult::skipped('Verify site ownership to enable this check.');
            } elseif (! $check->appliesTo($context)) {
                $result = CheckResult::skipped('Not applicable to this site.');
            } else {
                $result = $check->run($context);
            }

            $run->update([
                'status' => $result->status,
                'result' => $result->toArray(),
                'duration_ms' => $this->elapsedMs($started),
            ]);
        } catch (Throwable $e) {
            report($e);

            $run->update([
                'status' => CheckStatus::Error,
                'error' => Str::limit($e->getMessage(), 500),
                'duration_ms' => $this->elapsedMs($started),
            ]);
        }

        return true;
    }

    public function finish(Scan $scan): void
    {
        $this->reconciler->reconcile($scan);

        ScanCompleted::dispatch($scan->fresh());
    }

    public function fail(Scan $scan, string $message): void
    {
        $scan->update([
            'status' => ScanStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ]);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function elapsedMs(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
