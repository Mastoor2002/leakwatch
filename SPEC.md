# Leakwatch — Product Spec (v1)

> Working name. Easy to rename later (search the codebase for "Leakwatch" / "leakwatch").

## 1. One-sentence pitch

Leakwatch watches a team's Laravel (and other PHP) websites from the outside, finds the mistakes attackers scan the internet for — exposed `.env` files, debug mode left on, public admin dashboards, vulnerable packages — and tells you in plain English how to fix each one.

## 2. Who it's for

- **Primary:** web agencies and dev teams that run many Laravel apps (their own or their clients').
- **Secondary:** solo developers and small IT/security consultants who want a monthly "security check" report to hand to clients.

## 3. The demo story (what we show the hiring contact)

1. Sign up, create a team, add a client and its site.
2. Prove ownership of the site (DNS record or verification file).
3. Click "Scan" → watch checks complete → get a score (A–F) and a list of findings, each with severity, evidence, and the exact fix.
4. Install the tiny Leakwatch agent package into a Laravel app → the dashboard shows which installed packages have known vulnerabilities, and a fleet view answers: "Which of our sites are affected by this advisory?"
5. Download a clean PDF report you could send to a client.

The demo runs against a **deliberately vulnerable local test app we build ourselves** (Stage 9), never against someone else's site.

## 4. Ground rules (security & ethics — non-negotiable)

1. **Only verified sites get probed.** Before verification, the only thing we may do is a normal GET of the homepage plus DNS/TLS lookups (what any browser does). Every path probe (`/.env`, `/.git/HEAD`, `/telescope`, …) requires `verified_at` to be set.
2. **Never store secrets.** If we find an exposed `.env`, we record *that* it is exposed, the URL, status code, and at most the **names** of variables found (e.g. `APP_KEY`, `DB_PASSWORD`) — never their values. Response bodies are inspected in memory and discarded.
3. **SSRF protection.** Users type URLs, so the scanner must refuse to connect to private/internal addresses (127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16 incl. cloud metadata 169.254.169.254, ::1, fc00::/7, fe80::/10, 0.0.0.0/8, 100.64.0.0/10). Resolve the hostname first and check **every** resolved IP. All scanner HTTP goes through one `SafeHttp` service — no other code calls `Http::` for scanning. Exception: `LEAKWATCH_ALLOW_PRIVATE_TARGETS=true`, only honoured when `APP_ENV=local`, so we can scan our local demo app.
4. **Be polite.** Identify ourselves with a User-Agent (`LeakwatchBot/0.1 (+https://<app-url>/bot)`), 10-second timeouts, no redirects followed to other hosts, max ~30 requests per scan, small delay between requests.
5. **No exploitation.** We detect exposure; we never try to use what we find (no logging in, no decrypting, no RCE attempts).

## 5. Tech stack

- Laravel 13 (PHP 8.3+), Livewire starter kit (auth, profile, Flux UI components)
- SQLite locally (MySQL/Postgres in production)
- Queues: `database` driver locally (`php artisan queue:work`). Horizon + Redis only at deploy time (Horizon does not run on Windows).
- Pest for tests
- Laravel Sanctum for the agent API tokens (`php artisan install:api`)
- Laravel AI SDK (`laravel/ai`) for plain-English remediation (optional — the app must work fully without an AI key)
- `composer/semver` for matching package versions against advisories
- A PDF library (`spatie/laravel-pdf` or `barryvdh/laravel-dompdf` — pick whichever installs cleanly on the dev machine)
- Laravel Boost (dev) so Claude Code has version-correct Laravel docs

## 6. Data model

| Table | Key columns | Notes |
|---|---|---|
| users, teams, team membership | **from the Livewire starter kit (team support enabled)** | A team = an agency workspace. Reuse the kit's tables, roles and current-team logic; don't build a second team system. |
| clients | id, team_id, name, contact_email (nullable), notes | The agency's customer. |
| sites | id, client_id, team_id, url, host, verification_token, verification_method (`dns`/`file`, nullable), verified_at, is_laravel (nullable bool), last_scan_id, score, grade, agent_token_id (nullable) | `team_id` duplicated for easy scoping. `url` normalised to scheme+host. |
| scans | id, site_id, status (`queued`/`running`/`completed`/`failed`), trigger (`manual`/`scheduled`), started_at, finished_at, score, grade, error | |
| scan_check_runs | id, scan_id, check_key, status (`passed`/`failed`/`skipped`/`error`), duration_ms | Lets the UI show per-check progress. |
| findings | id, site_id, check_key, fingerprint, severity (`critical`/`high`/`medium`/`low`/`info`), title, summary, evidence (json, redacted), remediation (text), status (`open`/`resolved`/`ignored`), first_seen_scan_id, last_seen_scan_id, resolved_at, ignored_reason | One row per distinct problem on a site; updated across scans (not duplicated). `fingerprint` = hash(check_key + distinguishing detail, e.g. URL). |
| dependency_snapshots | id, site_id, reported_at, lock_hash, php_version, laravel_version | One per agent report. |
| installed_packages | id, snapshot_id, name, version | |
| advisories | id, package_name, advisory_id (PKSA/GHSA/CVE), title, cve, link, affected_versions, severity, reported_at | Cache of Packagist advisories. |
| package_findings | id, site_id, advisory_id, package_name, installed_version, status, first_seen_at, resolved_at | |
| ai_explanations | id, check_key, context_hash, body, model | Cache so we don't pay for the same explanation twice. |

Authorization: Policies everywhere. A user can only see teams they belong to; everything is scoped by `team_id`. Write tests proving user A cannot see team B's sites.

## 7. Scan engine

- `App\Scanning\Contracts\Check` interface:
  - `key(): string`, `name(): string`, `requiresVerification(): bool`, `appliesTo(ScanContext $ctx): bool`, `run(ScanContext $ctx): CheckResult`
  - Each check class also provides static `remediation(): string` (plain-English fix, used when AI is off).
- `ScanContext` holds the site, the `SafeHttp` client, the homepage response, and a **soft-404 baseline**: the response to a random path like `/leakwatch-<random>`. Many apps return `200` + their homepage for every path, so a check only counts a hit if the response clearly differs from the baseline AND matches the check's content markers.
- `CheckResult` = status + zero or more `FindingData` (severity, title, summary, evidence[], fingerprint detail).
- `RunScan` queued job: marks scan running → builds context → runs checks in order (recording `scan_check_runs`) → reconciles findings (new → create; still present → update `last_seen_scan_id`; previously open but not found now → mark `resolved`) → computes score → marks completed. A crash in one check records `error` for that check and the scan continues.
- Register checks in `config/leakwatch.php` so adding a check = new class + one config line.
- **Two ways to run a scan (config `leakwatch.scan_driver`, env `LEAKWATCH_SCAN_DRIVER`):**
  - `queue` (production): `RunScan` job runs all checks on a queue worker.
  - `inline` (local default, no worker needed): the scan is created as `queued`, and the site page's `wire:poll` calls a `ScanRunner::runNextCheck($scan)` step that runs **one** check per poll request (guarded by a cache lock so two polls never run the same check), then finalises when no checks remain. This gives live progress on a laptop without `queue:work` running.
  - Both drivers share the same `ScanRunner` service (start → runNextCheck → finish), so the logic exists once.

### Score

Start at 100. Subtract per **open** finding: critical 40, high 20, medium 8, low 2, info 0. Floor at 0. Grade: A ≥ 90, B ≥ 80, C ≥ 65, D ≥ 50, F < 50. Any open critical caps the grade at D.

## 8. Checks (v1)

| key | severity | detection (must avoid false positives) |
|---|---|---|
| `env_exposed` | critical | GET `/.env` and a short list of variants (`/.env.backup`, `/.env.bak`, `/.env.old`, `/.env.production`, `/laravel/.env`, `/api/.env`). Hit = 2xx, differs from soft-404 baseline, and body has ≥3 lines matching `^[A-Z][A-Z0-9_]*=`. Evidence = URL + list of variable **names** only. |
| `debug_mode` | critical | (a) GET `/_ignition/health-check` returns JSON containing `can_execute_commands`; (b) send a request likely to throw (e.g. `POST /` or `GET /?leakwatch[]=` against a GET route) and look for stack-trace markers: `Illuminate\\`, `vendor/laravel/framework`, `Whoops`, `Ignition`. **Verify the exact trigger against the local demo app with `APP_DEBUG=true` before trusting it.** |
| `telescope_exposed` | high | GET `/telescope` → 2xx and body contains Telescope markers (e.g. `telescope` app JS / title). |
| `horizon_exposed` | high | GET `/horizon` → 2xx + Horizon markers. |
| `pulse_exposed` | medium | GET `/pulse` → 2xx + Pulse markers. |
| `log_viewer_exposed` | high | GET `/log-viewer` (opcodesio/log-viewer) and `/logs` → 2xx + log-viewer markers. |
| `docroot_misconfigured` | critical | Web root points at project root instead of `/public`: GET `/composer.json` returns JSON with `"laravel/framework"`, or GET `/artisan` returns PHP source containing `Illuminate`. |
| `git_exposed` | high | GET `/.git/HEAD` body starts with `ref: refs/`. |
| `laravel_log_exposed` | high | GET `/storage/logs/laravel.log` → 2xx + lines like `[20xx-xx-xx ...] production.ERROR`. |
| `phpinfo_exposed` | medium | GET `/phpinfo.php`, `/info.php` → body contains `PHP Version` and `phpinfo()` table markers. |
| `php_version_eol` | medium | `X-Powered-By: PHP/x.y.z` header → compare to EOL table in `config/leakwatch.php` (verify dates at php.net/supported-versions). Also flag `low` that the header leaks the version at all. |
| `tls_certificate` | high (<7 days or invalid) / medium (<21 days) | Read cert with a stream context (`capture_peer_cert`), check expiry + hostname. Passive — allowed before verification. |
| `https_redirect` | medium | `http://host/` does not redirect to `https://`. Passive. |
| `security_headers` | low (each) | Missing `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options` *or* CSP `frame-ancestors`, `Referrer-Policy`. One finding per missing header. Passive. |
| `cookie_flags` | medium | `laravel_session` (or configured session cookie) missing `Secure`, `HttpOnly`, or `SameSite`. **Do not flag `XSRF-TOKEN` for missing HttpOnly — it is meant to be readable by JavaScript.** Passive. |
| `email_spf` | low | No SPF TXT record on the domain. Passive (DNS). |
| `email_dmarc` | medium (missing) / low (`p=none`) | `_dmarc.<domain>` TXT record. Passive (DNS). |
| `framework_detect` | info | Sets `sites.is_laravel` from `laravel_session`/`XSRF-TOKEN` cookies etc. Laravel-only checks use `appliesTo()` but still run if unknown. |

Out of scope for v1: port scanning, crawling, logged-in scans, brute force, anything that sends payloads.

## 9. Agent package (dependency monitoring)

- Lives in this repo at `packages/leakwatch/agent`, installed into other apps via a Composer path repository (later: publish to Packagist).
- `php artisan leakwatch:report` reads `composer.lock` (packages + packages-dev flagged), PHP version, Laravel version, and POSTs JSON to `POST /api/v1/agent/report` with `Authorization: Bearer <site token>` (Sanctum token, ability `agent:report`, one per site, shown once in the UI).
- The package schedules itself daily and can also run after deploy.
- Server side: store snapshot → for packages not checked recently, query Packagist's security advisories API (`https://packagist.org/api/security-advisories/` with `packages[]=…`, batch, cache ~6h) → match `affectedVersions` with `composer/semver` → create/resolve `package_findings`.
- **Fleet view:** list advisories affecting any site in the team, grouped by advisory, with the affected sites — "Which of our apps are hit by this?"

## 10. Ownership verification

- Each site gets a random `verification_token`.
- **DNS:** TXT record on the host: `leakwatch-verification=<token>` (use `dns_get_record($host, DNS_TXT)`).
- **File:** `https://<host>/.well-known/leakwatch-verification.txt` containing the token.
- "Verify" button checks both; on success set `verified_at` + method. Re-check monthly; un-verify if gone.

## 11. Notifications & scheduling

- Scheduler: weekly scan for every verified site (configurable per site: off/daily/weekly), daily cert-expiry check, daily advisory refresh.
- Notify team owners (mail; Slack incoming-webhook URL optional per team) when a scan finds a **new** critical/high finding, when a cert is within 14 days of expiry, or when a new advisory hits a site.

## 12. AI remediation (optional)

- `RemediationAdvisor` agent via Laravel AI SDK. Input: check key, finding title, redacted evidence, framework info. Output: short explanation (why it matters, in plain English) + exact fix steps (e.g. nginx rule, `.env` change, gate config).
- Never send secret values (there shouldn't be any stored anyway).
- Cache in `ai_explanations`. If no AI key configured, show the check's static `remediation()` text. UI shows a small "AI-written" label.

## 13. Pages (Livewire)

- Dashboard: team score overview, sites needing attention, latest critical findings, expiring certs.
- Clients: list / create / edit.
- Sites: list (grade, last scan, verified badge) / create / show.
- Site show: verify panel (if unverified), scan button + live progress (`wire:poll` while running), grade, findings grouped by severity (resolve / ignore with reason), scan history, dependencies tab, agent setup tab (token + install snippet), PDF download.
- Advisories (fleet view).
- Team settings: members (invite by email), Slack webhook, scan schedule defaults.

## 14. Definition of done for v1

- All checks have Pest tests using `Http::fake()` for hit, miss, and soft-404 false-positive cases.
- SafeHttp has tests for every blocked IP range and for DNS names resolving to private IPs.
- Policy tests prove cross-team isolation.
- A full scan of the local demo app finds the planted problems; a scan of a "clean" config finds none of them.
- README explains setup, the ethics rules, and how to run the demo.
