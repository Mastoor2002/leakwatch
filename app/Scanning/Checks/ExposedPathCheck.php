<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Base class for "this tool's page is public" checks (Telescope, Horizon, ...).
 * A hit needs a real 2xx (not the catch-all page) AND one of the tool's
 * unique markers in the body.
 */
abstract class ExposedPathCheck extends BaseCheck
{
    /**
     * @return array<int, string>
     */
    abstract protected function paths(): array;

    /**
     * @return array<int, string>
     */
    abstract protected function markers(): array;

    abstract protected function severity(): Severity;

    abstract protected function title(string $path): string;

    abstract protected function summary(): string;

    public function run(ScanContext $context): CheckResult
    {
        $findings = [];

        foreach ($this->paths() as $path) {
            $response = $context->fetch($path);

            if (! $context->isRealHit($response)) {
                continue;
            }

            $body = $response->body();
            $matched = array_values(array_filter($this->markers(), fn (string $marker) => stripos($body, $marker) !== false));

            if ($matched === []) {
                continue;
            }

            $findings[] = $this->finding(
                $this->severity(),
                $this->title($path),
                $this->summary(),
                ['url' => $context->url($path), 'status' => $response->status(), 'markers_found' => $matched],
                $path,
            );
        }

        return CheckResult::fromFindings($findings);
    }
}
