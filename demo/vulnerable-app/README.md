# Deliberately vulnerable demo site

**Local demos only. Never deploy this.** Every secret in `index.php` is fake.

This one PHP file behaves like a badly configured Laravel app so Surfacewatch has something to find:

| Mistake | Surfacewatch check |
|---|---|
| `/.env` downloadable (fake values) | `env_exposed` (critical) |
| Debug error page on `POST /` and `/_ignition/health-check` | `debug_mode` (critical) |
| `/composer.json` served (wrong document root) | `docroot_misconfigured` (critical) |
| `/.git/HEAD` downloadable | `git_exposed` (high) |
| `/storage/logs/laravel.log` readable | `laravel_log_exposed` (high) |
| `/telescope` open | `telescope_exposed` (high) |
| `/phpinfo.php` | `phpinfo_exposed` (medium) |
| `X-Powered-By: PHP/8.1.2` (end of life) | `php_version_eol` |
| No HTTPS, no security headers, weak cookies | `https_redirect`, `security_headers`, `cookie_flags` |

## Run it

```bash
cd demo/vulnerable-app
herd link vulnerable        # now served at http://vulnerable.test
```

In Surfacewatch's `.env` set `LEAKWATCH_ALLOW_PRIVATE_TARGETS=true` (only honoured when `APP_ENV=local`), because `vulnerable.test` resolves to 127.0.0.1 and the scanner blocks private addresses by default.

The demo seeder already created the site **vulnerable.test** with a matching verification token. Open it in Surfacewatch, click **Verify ownership** (the file method succeeds), then **Scan now**.
