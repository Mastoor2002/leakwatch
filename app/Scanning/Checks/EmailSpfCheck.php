<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

class EmailSpfCheck extends EmailCheck
{
    public function key(): string
    {
        return 'email_spf';
    }

    public function name(): string
    {
        return 'Email SPF record';
    }

    public function run(ScanContext $context): CheckResult
    {
        $domain = $this->domain($context);
        $spf = array_values(array_filter($context->dns->txt($domain), fn (string $record) => str_starts_with(strtolower(trim($record)), 'v=spf1')));

        if ($spf === []) {
            return CheckResult::failed($this->finding(
                Severity::Low,
                "No SPF record for {$domain}",
                'Without SPF, mail servers cannot tell which servers may send email for your domain, making spoofed emails (and phishing in your name) easier.',
                ['domain' => $domain],
                'missing',
            ));
        }

        if (preg_match('/\s\+all\b/i', $spf[0])) {
            return CheckResult::failed($this->finding(
                Severity::Medium,
                "SPF record for {$domain} allows any server (+all)",
                'The record authorises every server on the internet to send mail as your domain, which defeats the purpose of SPF.',
                ['domain' => $domain, 'record' => $spf[0]],
                'plus-all',
            ));
        }

        return CheckResult::passed();
    }

    public static function remediation(): string
    {
        return 'Publish a TXT record on the domain listing your mail providers, ending in `~all` or `-all`, for example: `v=spf1 include:_spf.google.com include:mailgun.org ~all`.';
    }
}
