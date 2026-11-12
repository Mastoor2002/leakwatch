# Leakwatch — Build Plan

> **Status (12 Nov 2026):** Stages 1–9 have been implemented. The app was written without being able to run it, so the first job is the **first-run checklist** below. After that, use these stage prompts to *understand* each part (ask Claude Code to "explain Stage N's code to me") and to extend it.

## First-run checklist (in a terminal in this folder)

```bash
composer leakwatch:setup        # migrate + demo data + npm run build (one command)
php artisan test               # run the whole suite; fix anything red
cd demo/vulnerable-app && herd link vulnerable && cd ../..
```

Then log in at http://leakwatch.test as demo@leakwatch.test / password, open **Sites → vulnerable.test**, click **Verify ownership**, then **Scan now**.

If a test fails, paste the failure into Claude Code with: "Fix this failing test without weakening the security rules in LEAKWATCH_RULES.md."

---

Build in stages. One stage per Claude Code session (or per `/clear`). After each stage: tests pass, you click through it in the browser, and you read the "What you built" summary until you understand it. **Don't skip the understanding part.** That's what gets you hired.

Copy each stage's prompt into Claude Code as written.

---

## Stage 0 — Set up (done)

The app was created with Herd at `C:\Users\Squash Club\Herd\leakwatch` (served at `http://leakwatch.test`) using: **Livewire** starter kit, Laravel's built-in auth, single-file components, **team support ON**, **Pest**, **Laravel Boost**, Git initialised, **SQLite**.

Still to do by hand:
1. Put `SPEC.md`, `BUILD_PLAN.md` in the project folder, and merge our `CLAUDE.md` rules into the one Boost created (keep both: Boost's Laravel guidelines and our Leakwatch rules).
2. Open `http://leakwatch.test`, register an account, check it works.
3. Make a **private** GitHub repo and push.
4. Open a terminal in the project folder and start Claude Code: `claude`

---

## Stage 1 — Teams, clients, sites

```
Read SPEC.md, CLAUDE.md and BUILD_PLAN.md. Do Stage 1 only.

The Livewire starter kit was installed WITH team support, so teams, membership, roles and "current team" already exist. First, read how the starter kit implements teams (models, migrations, how the current team is resolved) and explain it to me in simple words. Reuse it. Don't build a second team system.

Then build the clients and sites foundation from SPEC §6:
- Migrations, models, relationships, factories for clients and sites, both belonging to a team.
- Policies so users only see their current team's clients and sites. Pest tests prove user A can't view, edit or delete team B's client or site.
- Livewire pages: Clients (list/create/edit/delete), Sites (list/create/show) with URL validation that normalises to scheme+host and rejects non-http(s) URLs.
- Add sidebar links in the starter kit layout.
- Generate a random verification_token when a site is created.
Run tests and pint, then commit. End with the "What you built" summary.
```

**Check yourself:** Can you explain what a Policy is and where it gets called? What does `belongsTo` vs `hasMany` mean here?

---

## Stage 2 — SafeHttp + ownership verification

```
Do Stage 2 only (see SPEC §4 and §10).

1. Build App\Scanning\SafeHttp: a wrapper around Laravel's HTTP client that resolves the host, rejects any private/internal IP from SPEC §4.3 (IPv4 and IPv6, every resolved IP), sets our User-Agent, a 10s timeout, and doesn't follow redirects to a different host. Bypass only when LEAKWATCH_ALLOW_PRIVATE_TARGETS=true AND APP_ENV=local. Add a config/leakwatch.php file.
2. Pest tests for every blocked range, for a hostname resolving to 127.0.0.1, for 169.254.169.254, and for the bypass rules. Put DNS resolution behind an interface so tests can fake it.
3. Ownership verification: DNS TXT (leakwatch-verification=<token>) and file (/.well-known/leakwatch-verification.txt). A "Verify ownership" panel on the site page with copy-able instructions for both methods and a Verify button. Put DNS lookups behind an interface so tests can fake them.
Run tests and pint, commit, then give the "What you built" summary, and explain SSRF in simple words.
```

**Check yourself:** What is SSRF and why is it the #1 risk in a tool where users type in URLs?

---

## Stage 3 — Scan engine + first checks

```
Do Stage 3 only (SPEC §7 and §8).

Build the scan engine: Check contract, ScanContext (with soft-404 baseline), CheckResult/FindingData DTOs, severity/status enums, scans + scan_check_runs + findings migrations, a ScanRunner service (start / runNextCheck / finish), the RunScan queued job that uses it, the `inline` scan driver from SPEC §7 (one check per wire:poll request, cache-locked) as the local default, finding reconciliation (new / still open / resolved), and scoring + grade.

Implement these checks first, each with Pest tests for hit, miss and soft-404 false positive using Http::fake():
framework_detect, env_exposed, git_exposed, docroot_misconfigured, security_headers, https_redirect.

Path-probe checks must be skipped (status skipped) if the site isn't verified.
Add a "Scan now" button on the site page that dispatches RunScan.
Run tests and pint, commit, "What you built" summary, and explain how queued jobs work.
```

**Check yourself:** What happens between clicking "Scan now" and the results appearing? (Hint: job → queue table → worker.)

---

## Stage 4 — The rest of the checks

```
Do Stage 4 only. Implement the remaining v1 checks from SPEC §8, each with hit/miss/soft-404 tests:
debug_mode, telescope_exposed, horizon_exposed, pulse_exposed, log_viewer_exposed, laravel_log_exposed, phpinfo_exposed, php_version_eol, tls_certificate, cookie_flags, email_spf, email_dmarc.
TLS and DNS lookups go behind interfaces so they can be faked in tests.
Mark any detection rule you aren't sure about with a TODO to verify against the demo app in Stage 9.
Run tests and pint, commit, "What you built" summary.
```

---

## Stage 5 — Dashboard & results UI

```
Do Stage 5 only (SPEC §13).
- Site page: grade badge, live scan progress using wire:poll while a scan is queued/running (per-check status from scan_check_runs), findings grouped by severity with evidence, remediation text, and Resolve / Ignore (with reason) actions, plus scan history.
- Dashboard: team overview (average grade, counts by severity), sites needing attention, recent critical/high findings, certificates expiring soon.
- Use Flux UI components from the starter kit. Make it clean and professional. This is the demo screen.
Run tests and pint, commit, "What you built" summary, and explain how Livewire re-renders.
```

---

## Stage 6 — Agent package + vulnerable dependency alerts

```
Do Stage 6 only (SPEC §9).
1. Install Sanctum via php artisan install:api. Add an "Agent" tab on the site page that creates a per-site token (ability agent:report), shows it once, and shows install instructions.
2. Create the agent package at packages/leakwatch/agent (its own composer.json, service provider, a leakwatch:report artisan command, config for endpoint URL and token, and a daily schedule). It reads composer.lock and POSTs to /api/v1/agent/report.
3. Server: API endpoint with validation, store snapshot + installed packages, a queued job that fetches advisories from the Packagist security advisories API (batched, cached), matches versions with composer/semver, and creates or resolves package_findings.
4. Dependencies tab on the site page + an Advisories fleet page grouped by advisory with affected sites.
Tests: API auth (wrong token, wrong site), version matching edge cases, Http::fake for Packagist.
Run tests and pint, commit, "What you built" summary, and explain Sanctum tokens and Composer path repositories.
```

---

## Stage 7 — Scheduling & notifications

```
Do Stage 7 only (SPEC §11). Scheduled scans per site (off/daily/weekly), daily TLS expiry check, daily advisory refresh, and notifications (mail + optional Slack webhook per team) for new critical/high findings, cert expiring within 14 days, and new advisories. No duplicate alerts for the same finding. Tests with Notification::fake(). Commit, then the summary.
```

---

## Stage 8 — AI explanations + PDF report

```
Do Stage 8 only (SPEC §12).
1. Install the Laravel AI SDK (laravel/ai). Create a RemediationAdvisor agent. On a finding, an "Explain with AI" button generates a plain-English explanation + exact fix steps, cached in ai_explanations. If no AI provider key is configured, hide the button and show the static remediation text. Never send secrets. Test with the SDK's fakes.
2. PDF report per site: client name, date, grade, summary, findings by severity with fixes, dependency advisories. Add a download button.
Commit, then the summary.
```

---

## Stage 9 — Demo app, polish, README

```
Do Stage 9 only.
1. Create a separate tiny Laravel app in demo/vulnerable-app (NOT inside the main app's code) that is deliberately misconfigured for demos: APP_DEBUG=true, a FAKE .env publicly reachable (obviously fake values like DB_PASSWORD=demo-not-real), Telescope open, an exposed .git/HEAD, phpinfo.php, and no security headers. Give it a clear README warning: local demos only, never deploy.
2. Serve it locally with Herd (run `herd link vulnerable` inside demo/vulnerable-app so it's at http://vulnerable.test), add it as a site with LEAKWATCH_ALLOW_PRIVATE_TARGETS=true, verify it via the file method, and run a full scan. Fix any detection rule that misses or misfires (resolve the TODOs from Stage 4).
3. A demo seeder: a team with 3 clients and 6 sites with realistic scan history so the dashboard looks alive.
4. README.md: what it is, screenshots placeholder, setup, the ethics rules, how to run the demo.
Commit, then the summary.
```

---

## After v1 (only if it's going well)
- Deploy (Laravel Cloud or Forge), with Horizon + Redis for queues.
- Publish the agent package to Packagist.
- "Insurer view" report: map findings to common cyber-insurance questionnaire items.
- Support WordPress/Node apps (framework-specific check packs).
- Billing (Laravel Cashier) only if real users ask to pay.

## Before you show it to your contact
- Be able to explain: request lifecycle, Eloquent relationships, policies, queued jobs, Livewire components, Sanctum tokens, the SSRF guard, and why we don't store secrets.
- Never scan his company's site without his explicit permission. Offer to scan it live, with him, if he says yes.
