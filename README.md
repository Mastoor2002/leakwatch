# Leakwatch

**Find the leaks attackers scan for — before they do.**

Leakwatch monitors a team's Laravel (and other PHP) websites from the outside. It finds the mistakes botnets like Androxgh0st scan the internet for — exposed `.env` files, debug mode, public Telescope/Horizon dashboards, a wrong document root, readable logs, weak TLS, missing headers, insecure cookies, missing SPF/DMARC — and explains in plain English how to fix each one. A tiny Composer agent reports each app's installed packages so Leakwatch can alert when one of them gets a security advisory, across every client at once.

Built with Laravel 13, Livewire 4 (single-file components), Flux UI and Pest.

## Features

- Agencies → **clients** → **sites**, using the starter kit's teams, roles and invitations.
- **Ownership verification** (DNS TXT or `/.well-known/leakwatch-verification.txt`) before any path is probed.
- **18 checks** with live progress, A–F grade, findings that persist across scans (new → open → resolved → regressions reopen), ignore-with-reason.
- **Dependency monitoring** via the `leakwatch/agent` package + Packagist's security advisories API, and a fleet-wide **Advisories** page.
- **Alerts** by email and Slack for new critical/high issues, expiring certificates and new advisories — each alerted once.
- **Scheduled scans**, certificate checks, advisory refresh and monthly re-verification.
- **Printable client report** (Print → Save as PDF).
- Optional **AI explanations** of findings (Anthropic API, cached).

## Security design (the interview talking points)

| Risk | How Leakwatch handles it |
|---|---|
| SSRF — users type the URLs we fetch | Every request goes through `App\Scanning\SafeHttp`: DNS is resolved, **every** IP must be public (blocks 127/8, 10/8, 172.16/12, 192.168/16, 169.254.169.254, IPv6 private/link-local, IPv4-mapped IPv6), the connection is pinned to the checked IP (no DNS rebinding), only ports 80/443/8080/8443, redirects followed manually and never to another site. |
| Scanning sites you don't own | Path probes need verified ownership; unverified sites only get passive checks (homepage headers, TLS, DNS). |
| Leaking secrets we find | `.env` findings store variable **names** only. Bodies are never stored or logged. |
| False alarms | A random missing path is fetched first (the "soft-404 baseline"); catch-all 200 pages are never reported as exposed files. |
| Token theft | Agent tokens are shown once and stored as SHA-256 hashes; each token can only write to its own site. |
| Cross-team access | Policies on every page/action; site and finding lookups are scoped to the current team. |

## Run it locally (Herd)

```bash
composer install && npm install       # already done if Herd created the app
php artisan migrate --seed            # demo login: demo@leakwatch.test / password
npm run build
php artisan test
```

Open http://leakwatch.test.

### Demo: scan the deliberately vulnerable site

```bash
cd demo/vulnerable-app
herd link vulnerable                  # serves it at http://vulnerable.test
```

`.env` already has `LEAKWATCH_ALLOW_PRIVATE_TARGETS=true` (local only). Log in as the demo user → **Sites → vulnerable.test → Verify ownership → Scan now**. Expect 15+ findings including the exposed `.env`, debug mode, `.git`, Telescope and the log file.

### Try the agent

On a site's **Agent** tab create a token, then in any Laravel app:

```bash
composer config repositories.leakwatch path C:/Users/<you>/Herd/leakwatch/packages/leakwatch/agent
composer require leakwatch/agent:@dev
# .env: LEAKWATCH_ENDPOINT=http://leakwatch.test/api/v1/agent/report  LEAKWATCH_TOKEN=lw_...
php artisan leakwatch:report
```

### Background work

- Scheduled jobs: `php artisan schedule:work` locally, a cron entry in production.
- Production: set `LEAKWATCH_SCAN_DRIVER=queue` and run a queue worker (Horizon + Redis recommended).

## Where things live

| Path | What |
|---|---|
| `app/Scanning/SafeHttp.php` | The only outbound HTTP for scanning (SSRF guard) |
| `app/Scanning/Checks/` | One class per check |
| `app/Scanning/ScanRunner.php`, `ScanReconciler.php`, `Scoring.php` | Running scans, turning results into findings, grades |
| `app/Dependencies/` | Packagist advisory client + version range matcher |
| `resources/views/pages/` | Livewire single-file pages (sites, clients, advisories, alerts, dashboard) |
| `packages/leakwatch/agent/` | The Composer agent |
| `demo/vulnerable-app/` | Local-only vulnerable demo site |
| `tests/` | Pest tests: SSRF guard, every check (hit / miss / soft-404), scan lifecycle, access control, agent API, alerts |

See `SPEC.md` for the full specification and `DECISIONS.md` for why things are built this way.

## Mobile app (Android & iOS)

A React Native (Expo) companion app lives in [`mobile/`](mobile/README.md). It signs in against the versioned JSON API (`/api/v1`) with a personal access token, shows your fleet overview and per-site findings, and can start and follow a scan. It talks to **your own Leakwatch server over HTTPS**.

## API

`POST /api/v1/auth/token` (email + password → token), `GET /api/v1/me`, `GET /api/v1/teams/{team}/overview|sites|sites/{site}`, `POST|GET /api/v1/teams/{team}/sites/{site}/scan`. Tokens are prefixed `lwu_`, stored as SHA-256 hashes, and every endpoint is scoped to a team the user belongs to.

## HTTPS locally

With Herd: `herd secure leakwatch` (then browse `https://leakwatch.test`). In production, deploy behind HTTPS — see [`NEXT_STEPS.md`](NEXT_STEPS.md).

## Ethics

Leakwatch performs only passive checks on unverified sites and never exploits a finding. Use it only on sites you own or have written permission to test.

## License

MIT — see [LICENSE](LICENSE). Contributions welcome: [CONTRIBUTING.md](CONTRIBUTING.md).
