<?php

namespace App\Scanning;

use App\Enums\VerificationMethod;
use App\Models\Site;
use App\Scanning\Contracts\DnsResolver;
use Throwable;

/**
 * Proves a user controls a site before we run path probes against it.
 */
class SiteVerifier
{
    public ?string $lastError = null;

    public function __construct(
        private SafeHttp $http,
        private DnsResolver $dns,
    ) {
        //
    }

    public function verify(Site $site): ?VerificationMethod
    {
        $this->lastError = null;

        $method = match (true) {
            $this->viaDns($site) => VerificationMethod::Dns,
            $this->viaFile($site) => VerificationMethod::File,
            default => null,
        };

        $site->forceFill([
            'verification_checked_at' => now(),
            'verification_method' => $method,
            'verified_at' => $method ? ($site->verified_at ?? now()) : null,
        ])->save();

        return $method;
    }

    public function viaDns(Site $site): bool
    {
        $expected = config('leakwatch.verification.dns_prefix').$site->verification_token;

        foreach ($this->dns->txt($site->host) as $record) {
            if (trim($record, " \t\n\r\0\x0B\"") === $expected) {
                return true;
            }
        }

        return false;
    }

    public function viaFile(Site $site): bool
    {
        $url = rtrim($site->url, '/').config('leakwatch.verification.file_path');

        try {
            [$response] = $this->http->getFollowingRedirects($url);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $response->successful()
            && hash_equals($site->verification_token, trim($response->body()));
    }
}
