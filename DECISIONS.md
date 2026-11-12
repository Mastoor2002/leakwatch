# Decisions

One line per decision, newest last. Why things are the way they are.

- **Teams come from the Livewire starter kit** (team support enabled). Clients and sites belong to a team; no second team system.
- **Scan driver `inline` by default.** Each `wire:poll` request runs one check (cache-locked), so scans work on a laptop with no `queue:work`. `queue` driver runs the same `ScanRunner` in a `RunScan` job for production.
- **One HTTP gateway (`SafeHttp`) for all scanning traffic.** Resolves DNS, blocks private/metadata ranges for every resolved IP, pins the connection to the checked IP (`CURLOPT_RESOLVE`) to stop DNS rebinding, follows redirects manually and only on the same site, enforces a per-check request budget.
- **Private targets** are allowed only when `LEAKWATCH_ALLOW_PRIVATE_TARGETS=true` *and* `APP_ENV=local`, so the local demo site can be scanned but production can never be pointed at internal hosts.
- **Soft-404 baseline.** Each scan fetches a random missing path first; a 200 that looks like that page (or the homepage) is never treated as an exposed file.
- **Secrets are never stored.** `.env` findings keep variable names only; response bodies are inspected in memory and discarded.
- **Agent tokens without Sanctum.** One token per site, stored as a SHA-256 hash (like a password), checked by `AuthenticateAgent`. Simpler than Sanctum's user tokens for machine-to-machine auth, and no extra package.
- **Own version matcher instead of `composer/semver`.** `composer/semver` is only a dev dependency here; `App\Dependencies\VersionConstraint` handles Packagist's `>=a,<b|>=c,<d` ranges and is fully unit-tested.
- **AI explanations call the Anthropic Messages API through Laravel's HTTP client.** The first-party Laravel AI SDK (`laravel/ai`) wasn't installed when this was built; swapping to it later only touches `App\Ai\RemediationAdvisor`. The feature is optional and hidden without `ANTHROPIC_API_KEY`.
- **PDF report = printable HTML.** `/sites/{site}/report` is a standalone print-styled page; "Print / Save as PDF" in the browser produces the PDF. No PDF library needed.
- **Slack alerts via a tiny custom notification channel** (`SlackWebhookChannel`) that only accepts `https://hooks.slack.com/services/...` URLs, so it can't be abused for SSRF.
- **PHP end-of-life lookup reads the whole map.** `config('leakwatch.php_eol.8.1')` would treat "8.1" as nested keys, so the check reads the array and indexes it.
- **Grade cap:** any open critical finding caps the grade at D regardless of score.
