<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

class EmailDmarcCheck extends EmailCheck
{
    public function key(): string
    {
        return 'email_dmarc';
    }

    public function name(): string
    {
        return 'Email DMARC policy';
    }

    public function run(ScanContext $context): CheckResult
    {
        $domain = $this->domain($context);
        $records = array_values(array_filter($context->dns->txt("_dmarc.{$domain}"), fn (string $record) => str_starts_with(strtolower(trim($record)), 'v=dmarc1')));

        if ($records === []) {
            return CheckResult::failed($this->finding(
                Severity::Medium,
                "No DMARC policy for {$domain}",
                'Gmail, Yahoo and Microsoft now expect DMARC from bulk senders. Without it, spoofed emails using your domain are more likely to reach inboxes, and your own mail may land in spam.',
                ['domain' => $domain],
                'missing',
            ));
        }

        if (preg_match('/\bp\s*=\s*none\b/i', $records[0])) {
            return CheckResult::failed($this->finding(
                Severity::Low,
                "DMARC policy for {$domain} is monitor-only (p=none)",
                'Receivers report spoofed mail but still deliver it. Move to quarantine or reject once reports look clean.',
                ['domain' => $domain, 'record' => $records[0]],
                'p-none',
            ));
        }

        return CheckResult::passed();
    }

    public static function remediation(): string
    {
        return 'Publish a TXT record at `_dmarc.yourdomain.com`, start with `v=DMARC1; p=none; rua=mailto:dmarc@yourdomain.com`, review the reports for a few weeks, then move to `p=quarantine` and finally `p=reject`.';
    }
}
