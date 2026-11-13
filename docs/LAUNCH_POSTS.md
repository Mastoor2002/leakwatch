# Launch drafts (edit before posting — only post what's true)

## r/laravel (flair: Package / Tool)
**Title:** I built an external security scanner for Laravel sites (exposed .env, debug mode, Telescope/Horizon…) — feedback welcome

I'm a cybersecurity student and built Leakwatch, a Laravel 13 + Livewire app for agencies that run many client sites. It checks from the outside for the mistakes botnets scan for: exposed `.env`/`.git`, debug mode, public Telescope/Horizon/Pulse, wrong document root, readable logs, weak TLS/headers/cookies, missing SPF/DMARC. A small Composer agent reports installed packages so it can alert on new Packagist advisories across every client.

The part I'm proudest of is the security design of the scanner itself:
- All outbound requests go through one `SafeHttp` class: DNS is resolved, every IP must be public, the connection is pinned to the checked IP (no DNS rebinding), redirects never leave the site.
- Path probes only run on sites whose ownership is verified (DNS TXT or a well-known file).
- A soft-404 baseline avoids false "exposed file" alarms; `.env` findings store variable names only, never values.

231 Pest tests. There's also an Expo mobile app on a versioned `/api/v1`. Try it live: https://leakwatch.tech. MIT licensed: https://github.com/Mastoor2002/leakwatch

Happy to hear what checks you'd add or where the SSRF defence could be tighter.

## Laravel News Links
Title: Leakwatch – external exposure monitor for Laravel sites (open source)
Link: https://github.com/Mastoor2002/leakwatch
Category: Package/Project. One-liner: Finds exposed .env, debug mode and public dashboards across client sites, with an SSRF-hardened scanner and Packagist advisory monitoring.

## Show HN
**Title:** Show HN: Leakwatch – external exposure monitor for Laravel sites
First comment: what it checks, the SSRF/ownership-verification design, what's missing (a few more checks and rate-limit tuning), and a request for feedback.

## Blog post outline — "How I stopped my security scanner becoming an SSRF vector in Laravel"
1. The risk: users type URLs your server fetches. 2. DNS resolve + every-IP check. 3. Pinning against rebinding. 4. Redirect rules. 5. Ownership verification gate. 6. How I tested it (Pest + fake DNS).

## LinkedIn
Built and open-sourced Leakwatch, a Laravel security exposure monitor with an Expo mobile app. Focus: a scanner that can't be turned against internal networks (SSRF-hardened), ownership-verified probing, no secrets stored. Live: https://leakwatch.tech · Repo: https://github.com/Mastoor2002/leakwatch — feedback welcome.
