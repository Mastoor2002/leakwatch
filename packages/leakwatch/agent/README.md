# leakwatch/agent

Reports a Laravel application's installed Composer packages (names and versions only) to Leakwatch, which matches them against Packagist security advisories and alerts you when a package you run gets a new CVE.

## Install

```bash
# from Packagist (once published)
composer require leakwatch/agent

# or, for the local demo, from this folder
composer config repositories.leakwatch path /path/to/leakwatch/packages/leakwatch/agent
composer require leakwatch/agent:@dev
```

Add to `.env` (the token is shown once on the site's **Agent** tab in Leakwatch):

```
LEAKWATCH_ENDPOINT=https://your-leakwatch.test/api/v1/agent/report
LEAKWATCH_TOKEN=lw_...
```

Send the first report, then it runs daily through the scheduler:

```bash
php artisan leakwatch:report            # send
php artisan leakwatch:report --dry-run  # see exactly what would be sent
```

## Privacy

Only `composer.lock` is read. The agent sends package names, versions, whether each is a dev dependency, the PHP and Laravel versions, and a hash of the lock file. It never reads or sends source code, configuration or `.env` values.
