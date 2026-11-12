<?php

namespace App\Scanning\Checks;

use App\Enums\Severity;
use App\Scanning\Data\CheckResult;
use App\Scanning\ScanContext;

/**
 * The #1 Laravel leak: a public .env file (database passwords, APP_KEY, API keys).
 * This is exactly what the Androxgh0st botnet scans the internet for.
 *
 * We only record that the file is exposed and the NAMES of the variables in
 * it. Values are never stored or logged.
 */
class EnvExposedCheck extends BaseCheck
{
    /**
     * @var array<int, string>
     */
    public const PATHS = [
        '/.env',
        '/.env.backup',
        '/.env.bak',
        '/.env.old',
        '/.env.production',
        '/.env.save',
        '/laravel/.env',
        '/api/.env',
    ];

    public function key(): string
    {
        return 'env_exposed';
    }

    public function name(): string
    {
        return 'Exposed .env files';
    }

    public function run(ScanContext $context): CheckResult
    {
        $findings = [];

        foreach (self::PATHS as $path) {
            $response = $context->fetch($path);

            if (! $context->isRealHit($response)) {
                continue;
            }

            $names = self::variableNames($response->body());

            if (count($names) < 3 || ScanContext::looksLikeHtml($response->body())) {
                continue;
            }

            $sensitive = array_values(array_intersect($names, [
                'APP_KEY', 'DB_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'MAIL_PASSWORD', 'STRIPE_SECRET',
                'REDIS_PASSWORD', 'PUSHER_APP_SECRET', 'OPENAI_API_KEY', 'ANTHROPIC_API_KEY',
            ]));

            $findings[] = $this->finding(
                Severity::Critical,
                "Environment file publicly readable at {$path}",
                'Anyone can download this file. Laravel .env files usually contain database passwords, the APP_KEY (which can lead to remote code execution) and third-party API keys. Automated botnets such as Androxgh0st look for exactly this.',
                [
                    'url' => $context->url($path),
                    'status' => $response->status(),
                    'variable_count' => count($names),
                    'sensitive_variables_present' => $sensitive,
                    'variable_names' => array_slice($names, 0, 40),
                    'note' => 'Values were not stored. Treat every secret in this file as compromised.',
                ],
                $path,
            );
        }

        return CheckResult::fromFindings($findings);
    }

    /**
     * Names of KEY=value lines. Values are discarded immediately.
     *
     * @return array<int, string>
     */
    public static function variableNames(string $body): array
    {
        preg_match_all('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]{1,63})\s*=/m', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function remediation(): string
    {
        return <<<'TXT'
1. Block the file right now. Nginx: add `location ~ /\.(?!well-known).* { deny all; }` to the server block. Apache: make sure the document root is the `public/` folder and add `<FilesMatch "^\.">Require all denied</FilesMatch>`.
2. Make sure the web server's document root points at Laravel's `public/` directory, not the project root.
3. Assume every secret in the file is stolen: run `php artisan key:generate` (keep the old key in `APP_PREVIOUS_KEYS` so existing data can still be decrypted), then rotate the database password, mail password and every API key listed.
4. Delete any backup copies (`.env.bak`, `.env.old`, ...) from the server.
TXT;
    }
}
