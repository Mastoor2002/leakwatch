# Leakwatch — project rules for coding agents

Read `SPEC.md` (what we're building) and `BUILD_PLAN.md` (stages) before doing any work. Only do the stage(s) you are asked for.

## Working mode
- Work autonomously. Do not stop to ask questions. When something is ambiguous, pick the most conventional Laravel option, and record the decision in one line in `DECISIONS.md`.
- Before finishing a task: run the relevant tests (`php artisan test --compact`), run `vendor/bin/pint --dirty`, run `npm run build` if you changed any Blade/CSS/JS, and commit with a clear message.
- End every task with a short **"What you built & the Laravel concepts used"** section in simple words (the owner is learning Laravel and must be able to explain this app in an interview).

## Stack
Laravel 13 · PHP 8.5 (Herd) · Livewire 4 starter kit with single-file components (⚡ files) and Flux UI · **starter-kit teams** (reuse them; never build a second team system) · SQLite · Pest · Sanctum (agent API) · Laravel AI SDK (optional feature) · database queue in production, `inline` scan driver locally.

Use the Laravel Boost MCP tools and the skills in `.claude/skills` to check version-correct docs and conventions instead of guessing APIs.

## Hard rules (security — never break these)
1. All outbound scanner HTTP goes through `App\Scanning\SafeHttp`. Never call `Http::` directly in scanning code.
2. SafeHttp blocks private/internal IPs (SPEC §4.3) after DNS resolution, for every resolved IP. The only bypass is `LEAKWATCH_ALLOW_PRIVATE_TARGETS=true` **and** `APP_ENV=local`.
3. Path probes (anything other than homepage GET, DNS, TLS) only run on sites with `verified_at` set.
4. Never persist secret values. If a probe finds a `.env`, store variable **names** only. Never log response bodies.
5. Never write code that exploits a finding (no decrypting, logging in, payload delivery).
6. Every query is scoped to the current team; use policies for every route and Livewire action.

## Code conventions
- Follow the starter kit's existing patterns (check sibling files): single-file Livewire page components in `resources/views/pages/...`, routes in `routes/web.php` under the team-scoped route group, Flux components for UI.
- Checks live in `app/Scanning/Checks/`, one class per check, implementing `App\Scanning\Contracts\Check`, registered in `config/leakwatch.php`.
- Every check has Pest tests for: a real hit, a clean miss, and the soft-404 false positive.
- Enums for statuses/severities in `app/Enums`.
- Small, reversible migrations. Never edit a committed migration; add a new one.
