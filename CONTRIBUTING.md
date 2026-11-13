# Contributing to Leakwatch

Thanks for helping! Quick rules:

1. Read `SPEC.md` and `LEAKWATCH_RULES.md` first — the **security hard rules** are not negotiable (all scanner HTTP goes through `App\Scanning\SafeHttp`, no secret values are ever stored, path probes only on verified sites).
2. New checks live in `app/Scanning/Checks/`, implement `App\Scanning\Contracts\Check`, are registered in `config/leakwatch.php`, and need Pest tests for a real hit, a clean miss and the soft-404 false positive.
3. Run `php artisan test --compact` and `vendor/bin/pint --dirty` before opening a PR.
4. Please report security issues privately (GitHub Security Advisories) rather than in a public issue.

Leakwatch must only be used against sites you own or are authorised to test.
