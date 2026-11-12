<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\Exceptions\BlockedTargetException;
use App\Scanning\ScanContext;

/**
 * Passive: connects to port 443 and reads the certificate.
 */
class TlsCertificateCheck extends BaseCheck
{
    public function key(): string
    {
        return 'tls_certificate';
    }

    public function name(): string
    {
        return 'TLS certificate';
    }

    public function requiresVerification(): bool
    {
        return false;
    }

    public function appliesTo(ScanContext $context): bool
    {
        return $context->isHttps();
    }

    public function run(ScanContext $context): CheckResult
    {
        $host = $context->host();

        try {
            $ip = $context->http->assertHostAllowed($host)[0];
        } catch (BlockedTargetException $e) {
            return CheckResult::skipped($e->getMessage());
        }

        $port = (int) (parse_url($context->origin, PHP_URL_PORT) ?: 443);
        $certificate = $context->tls->inspect($host, $ip, $port);

        if ($certificate === null) {
            return CheckResult::failed($this->finding(
                Severity::High,
                'HTTPS connection failed',
                'The site is configured for https:// but a TLS connection could not be made, so visitors will see an error.',
                ['host' => $host, 'port' => $port],
                'connect',
            ));
        }

        $evidence = [
            'subject' => $certificate->subject,
            'issuer' => $certificate->issuer,
            'expires_at' => $certificate->validTo->toDateString(),
            'days_remaining' => $certificate->daysRemaining(),
        ];

        if ($certificate->isExpired()) {
            return CheckResult::failed($this->finding(
                Severity::High,
                'TLS certificate has expired',
                'Browsers block visitors with a full-page warning, and API clients refuse to connect.',
                $evidence,
                'expired',
            ));
        }

        if (! $certificate->trusted) {
            return CheckResult::failed($this->finding(
                Severity::High,
                'TLS certificate is not trusted',
                'The certificate is self-signed, issued for another name, or missing intermediate certificates. Browsers show a security warning.',
                $evidence + ['error' => $certificate->error],
                'untrusted',
            ));
        }

        $days = $certificate->daysRemaining();

        if ($days < 7) {
            return CheckResult::failed($this->finding(
                Severity::High,
                "TLS certificate expires in {$days} day(s)",
                'Automatic renewal looks broken. When it expires, browsers will block the site.',
                $evidence,
                'expiring',
            ));
        }

        if ($days < 21) {
            return CheckResult::failed($this->finding(
                Severity::Medium,
                "TLS certificate expires in {$days} days",
                'Certificates from automated issuers normally renew 30 days before expiry. Check the renewal job.',
                $evidence,
                'expiring',
            ));
        }

        return CheckResult::passed("Valid until {$certificate->validTo->toDateString()}.");
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Renew the certificate now (Let's Encrypt: `certbot renew`, or "Renew" in Forge / your host's panel).
2. Check the renewal cron/systemd timer is running and that port 80 is reachable for the HTTP challenge.
3. Serve the full certificate chain (`fullchain.pem`, not `cert.pem`) so all clients trust it.
TXT;
    }
}
