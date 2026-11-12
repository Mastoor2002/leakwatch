<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Scanning\SiteVerifier;
use Illuminate\Console\Command;

class ReverifySites extends Command
{
    protected $signature = 'leakwatch:reverify-sites';

    protected $description = 'Re-check ownership of verified sites; un-verify sites whose proof was removed';

    public function handle(SiteVerifier $verifier): int
    {
        Site::query()
            ->whereNotNull('verified_at')
            ->where(fn ($query) => $query->whereNull('verification_checked_at')->orWhere('verification_checked_at', '<', now()->subDays(30)))
            ->each(function (Site $site) use ($verifier) {
                if ($verifier->verify($site) === null) {
                    $this->warn("{$site->host} is no longer verified.");
                }
            });

        return self::SUCCESS;
    }
}
