# Leakwatch — what's left, and how to get it seen

## A. Still to do (needs you / your accounts)
1. **Push to GitHub.** The Claude GitHub App isn't installed on `Mastoor2002/leakwatch`, so my push returned 403. Either install it (https://github.com/apps/claude/installations/select_target → pick the repo) or run on your PC in `C:\Users\Squash Club\Herd\leakwatch`:
   `git remote add origin https://github.com/Mastoor2002/leakwatch.git && git push -u origin main`
   Check `.env` is NOT committed (it's git-ignored). Make the repo public in Settings → General → Danger zone.
2. **Verify the new API.** Run `php artisan migrate` then `php artisan test --compact`. The 218 existing tests passed; the 13 new API tests in `tests/Feature/Api/MobileApiTest.php` were written but never run (no PHP in my workspace). Fix anything that fails.
3. **Run the mobile app on a phone** (`mobile/README.md`). It type-checks and bundles for Android and iOS but has not been tried on a device.
4. **Real HTTPS deployment** (this removes the "Not secure" warning — that warning is only because `leakwatch.test` is plain http locally; `herd secure leakwatch` fixes it locally).
5. **Store builds** need your accounts (below). I can't create them for you.
6. Add 3–4 screenshots + a 60-second GIF to the README (biggest single boost for GitHub impressions).
7. Optional: token-management page in the web UI (tokens can currently be obtained through `/api/v1/auth/token`).

## B. Hosting with real HTTPS (researched Nov 2026)
- **Laravel Cloud** — Starter plan is $5/month plus usage with the first month free; new orgs also get $5 free credit with no card needed ([laravel.com/cloud/pricing](https://laravel.com/cloud/pricing), [marketing.cloud.laravel.com/pricing](https://marketing.cloud.laravel.com/pricing)). Easiest path; custom domains and HTTPS included. Note: the scan queue/scheduler cost extra, and the DB is often the biggest bill item on the $5 tier ([dev.to](https://dev.to/deploynix/laravel-clouds-5-plan-vs-a-5-vps-the-real-cost-math-4dm0)).
- **Forge + a VPS** — Forge Hobby is about $12/mo plus the server ([laracopilot.com](https://laracopilot.com/?p=2870)); full control, flat cost.
- Whichever you pick: set `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database` with a worker, run `schedule:run` every minute, and **never** set `LEAKWATCH_ALLOW_PRIVATE_TARGETS`.
- Mobile apps then use that `https://` URL on the login screen.

## C. App stores
- Apple Developer Program is $99/year; Google Play is a one-time $25 registration (~$124 for both in year one) ([choicely.com](https://www.choicely.com/tutorials/how-much-does-it-cost-to-publish-an-app), [axonbuild.com](https://axonbuild.com/blog/cost-to-publish-an-app-to-the-app-stores/)). Google may ask for ID verification.
- Cheapest path for a portfolio: **Android APK via `eas build --profile preview`** (free to sideload; attach to a GitHub Release) and a short screen-recording for iOS. EAS builds iOS in the cloud from Windows, but device-installable iOS builds need the paid Apple program ([expo.dev](https://expo.dev/blog/apple-developer-program-explained-free-vs-paid)). Expo's free tier included roughly 15 iOS + 15 Android builds/month at last check — confirm on expo.dev.
- Honest framing: this is a companion app for a self-hosted server, so store review may ask for a demo login — prepare a demo server + demo account.

## D. Getting noticed (low-effort, in order)
1. **Polish the repo first**: clear one-line pitch, screenshots/GIF, "Quick start", MIT license (done), topics `laravel livewire security scanner php`, pinned repo on your profile.
2. **Laravel News Links** — anyone can register and submit a link to a package or project ([laravel-news.com/links](https://laravel-news.com/links/new), announced at [laravel-news.com/laravel-links](https://laravel-news.com/index.php/laravel-links)). Similar security tools (e.g. "Larascan", "Laravel SecureScan") were submitted there recently, so lead with what's different: external scanning, soft-404 handling, SSRF-hardened scanner, agent + advisories, mobile app. There is also a code-submission form at [laravel-news.com/submit](https://laravel-news.com/submit).
3. **r/laravel** — the sub has a "Package / Tool" flair and regularly hosts self-made projects. Post a genuine "I built this, feedback welcome" with the technical story (SSRF design, soft-404 baseline), not an ad. Read the sub rules first; I couldn't retrieve them.
4. **Packagist** — publish the `leakwatch/agent` package in `packages/` as its own repo + Packagist entry (needs your GitHub login). Gives a second discoverable artifact.
5. **Write-ups** — one dev.to/Medium/personal-blog post: "How I stopped my scanner becoming an SSRF vector in Laravel". Cross-post and submit to Laravel News Links. Security-engineering stories are your edge as a cybersecurity student.
6. **Show HN** — "Show HN: Leakwatch – external exposure monitor for Laravel sites" works best with a repo link, no signup, and you answering comments; weekday morning US time. A front-page dev tool post is reported at roughly 5k–30k visits ([hub.causo.ai](https://hub.causo.ai/guides/show-hn-launch-playbook-technical-founders-2026)). Don't expect many users — the credibility is the point.
7. **Product Hunt** is optional; developer-tool commentary in 2026 is that it's less useful than HN/GitHub alone ([dev.to](https://dev.to/lightningdev123/beyond-product-hunt-a-technical-launch-guide-for-2026-i2j)). Skip unless you have spare time.
8. **X/LinkedIn** — short demo video, tag nobody spammy; engage in Laravel threads for a few weeks beforehand.
9. **Applications** — put the repo at the top of your résumé and LinkedIn, and write 3 bullets (SSRF defence, ownership-verified probing, 218+ tests). Offer a 5-minute walkthrough to the Laravel-using company contact.

## E. Safety note for launches
Only scan sites you own. Keep the public demo pointed at your own `vulnerable.test`-style target; never advertise scanning third-party sites.
