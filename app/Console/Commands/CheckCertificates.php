<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Notifications\CertificateExpiring;
use App\Scanning\Contracts\TlsInspector;
use App\Scanning\SafeHttp;
use Illuminate\Console\Command;
use Throwable;

class CheckCertificates extends Command
{
    protected $signature = 'leakwatch:check-certificates';

    protected $description = 'Warn teams about TLS certificates that expire soon';

    public function handle(SafeHttp $http, TlsInspector $tls): int
    {
        $warnDays = (int) config('leakwatch.certificate_warning_days', 14);

        Site::query()->where('url', 'like', 'https://%')->each(function (Site $site) use ($http, $tls, $warnDays) {
            try {
                $ip = $http->assertHostAllowed($site->host)[0];
                $port = (int) (parse_url($site->url, PHP_URL_PORT) ?: 443);
                $certificate = $tls->inspect($site->host, $ip, $port);
            } catch (Throwable) {
                return;
            }

            if ($certificate === null || $certificate->daysRemaining() > $warnDays) {
                return;
            }

            // One alert per certificate (a renewed cert has a new expiry date).
            if ($site->certificate_alerted_for?->toDateString() === $certificate->validTo->toDateString()) {
                return;
            }

            CertificateExpiring::send($site, $certificate);
            $site->forceFill(['certificate_alerted_for' => $certificate->validTo->toDateString()])->save();
            $this->line("Alerted for {$site->host} ({$certificate->daysRemaining()} days left)");
        });

        return self::SUCCESS;
    }
}
