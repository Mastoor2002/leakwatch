<?php

/**
 * ============================================================================
 *  DELIBERATELY VULNERABLE DEMO SITE - FOR LOCAL LEAKWATCH DEMOS ONLY
 * ============================================================================
 *
 *  This single file imitates a badly configured Laravel app so Leakwatch has
 *  something to find. Every "secret" below is fake. Never deploy this file to
 *  a server that is reachable from the internet.
 *
 *  Serve it with Herd:   cd demo/vulnerable-app && herd link vulnerable
 *  Then it lives at:     http://vulnerable.test
 */

// Must match LEAKWATCH_DEMO_TOKEN / config('leakwatch.demo_verification_token').
const VERIFICATION_TOKEN = 'lw-demo-6f1c2b8e9a4d4c1f';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Mistakes on every response: PHP version leak, no security headers, weak cookies.
header('X-Powered-By: PHP/8.1.2');
header('Set-Cookie: XSRF-TOKEN=demo-xsrf-'.bin2hex(random_bytes(8)).'; path=/', false);
header('Set-Cookie: vulnerable_demo_session=demo-'.bin2hex(random_bytes(8)).'; path=/', false);

function send(int $status, string $contentType, string $body): never
{
    http_response_code($status);
    header("Content-Type: {$contentType}");
    echo $body;
    exit;
}

if ($path === '/.well-known/leakwatch-verification.txt') {
    send(200, 'text/plain', VERIFICATION_TOKEN);
}

if ($path === '/' && $method === 'POST') {
    // What Laravel shows when APP_DEBUG=true and a route doesn't accept POST.
    send(405, 'text/html; charset=UTF-8', <<<'HTML'
<!DOCTYPE html><html><head><title>Method Not Allowed</title></head><body>
<h1>Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException</h1>
<p>The POST method is not supported for route /. Supported methods: GET, HEAD.</p>
<ol>
<li>vendor/laravel/framework/src/Illuminate/Routing/AbstractRouteCollection.php:123</li>
<li>vendor/laravel/framework/src/Illuminate/Routing/RouteCollection.php:162</li>
<li>Illuminate\Routing\Router->dispatchToRoute()</li>
</ol>
</body></html>
HTML);
}

if ($path === '/') {
    send(200, 'text/html; charset=UTF-8', <<<'HTML'
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="csrf-token" content="demo-token">
<title>Bluebird Bakery (demo)</title></head>
<body style="font-family: sans-serif; max-width: 640px; margin: 60px auto;">
<h1>Bluebird Bakery</h1>
<p>Fresh bread daily. <em>This is a deliberately misconfigured demo site for Leakwatch.</em></p>
</body></html>
HTML);
}

$files = [
    '/.env' => ['text/plain', <<<'ENV'
APP_NAME="Bluebird Bakery"
APP_ENV=production
APP_KEY=base64:FAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAK=
APP_DEBUG=true
APP_URL=http://vulnerable.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=bakery
DB_USERNAME=bakery
DB_PASSWORD=demo-not-a-real-password
MAIL_PASSWORD=demo-not-a-real-password
STRIPE_SECRET=sk_test_demo_not_real
ENV],
    '/.git/HEAD' => ['text/plain', "ref: refs/heads/main\n"],
    '/storage/logs/laravel.log' => ['text/plain', "[2026-11-10 14:22:31] production.ERROR: SQLSTATE[HY000] [2002] Connection refused (demo log entry)\n[2026-11-10 14:22:35] production.INFO: Order 1042 paid (demo log entry)\n"],
    '/composer.json' => ['application/json', '{"name": "bluebird/shop", "require": {"php": "^8.1", "laravel/framework": "^10.0"}}'],
    '/_ignition/health-check' => ['application/json', '{"can_execute_commands":true}'],
    '/telescope' => ['text/html; charset=UTF-8', '<!DOCTYPE html><html><head><title>Telescope - Bluebird Bakery</title></head><body><div id="telescope"></div><script>window.Telescope = {"path":"telescope","timezone":"UTC","recording":true};</script><script src="/vendor/telescope/app.js"></script></body></html>'],
    '/phpinfo.php' => ['text/html; charset=UTF-8', '<!DOCTYPE html><html><head><title>PHP 8.1.2 - phpinfo()</title></head><body><h1 class="p">PHP Version 8.1.2</h1><p>(Demo page - not real phpinfo() output.)</p></body></html>'],
];

if (isset($files[$path])) {
    send(200, $files[$path][0], $files[$path][1]);
}

send(404, 'text/html; charset=UTF-8', '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 Not Found</h1></body></html>');
