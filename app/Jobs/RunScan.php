<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Scanning\ScanRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Production path: a queue worker runs the whole scan in the background.
 */
class RunScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Scan $scan)
    {
        //
    }

    public function handle(ScanRunner $runner): void
    {
        $runner->runToCompletion($this->scan);
    }

    public function failed(?Throwable $exception): void
    {
        app(ScanRunner::class)->fail($this->scan, 'Scan crashed: '.($exception?->getMessage() ?? 'unknown error'));
    }
}
