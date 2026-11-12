<?php

namespace App\Scanning\Checks;

use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * Works out whether the site runs Laravel, from the homepage only.
 * Doesn't create findings; it records site.is_laravel for the dashboard.
 */
class FrameworkDetectCheck extends BaseCheck
{
    public function key(): string
    {
        return 'framework_detect';
    }

    public function name(): string
    {
        return 'Detect framework';
    }

    public function requiresVerification(): bool
    {
        return false;
    }

    public function appliesTo(ScanContext $context): bool
    {
        return $context->homepage !== null;
    }

    public function run(ScanContext $context): CheckResult
    {
        $signals = [];

        foreach ($context->homepage->cookies() as $cookie) {
            if ($cookie['name'] === 'XSRF-TOKEN') {
                $signals[] = 'XSRF-TOKEN cookie';
            } elseif (str_ends_with($cookie['name'], '_session')) {
                $signals[] = "{$cookie['name']} cookie";
            }
        }

        if ($context->homepage->hasMarker('csrf_meta')) {
            $signals[] = 'csrf-token meta tag';
        }

        if ($context->homepage->hasMarker('livewire')) {
            $signals[] = 'Livewire markup';
        }

        $isLaravel = in_array('XSRF-TOKEN cookie', $signals, true)
            || count($signals) >= 2;

        $context->site->forceFill(['is_laravel' => $isLaravel])->save();

        return CheckResult::passed($isLaravel
            ? 'Laravel detected ('.implode(', ', array_unique($signals)).').'
            : 'Laravel not detected from the homepage.');
    }

    public static function remediation(): string
    {
        return 'No action needed.';
    }
}
