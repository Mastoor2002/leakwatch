<?php

namespace App\Scanning;

use App\Scanning\Contracts\DnsResolver;
use App\Scanning\Exceptions\BlockedTargetException;
use App\Scanning\Exceptions\RequestBudgetExceededException;
use App\Scanning\Net\IpGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The ONLY way scanning code talks to the outside world.
 *
 * Because users type in the URLs we fetch, every request is checked first:
 *  - only http/https on normal web ports,
 *  - the host is resolved and EVERY resolved IP must be public (no 127.x,
 *    10.x, 169.254.169.254 cloud metadata, etc.) - this stops SSRF,
 *  - the connection is pinned to the IP we checked, so DNS can't be switched
 *    between our check and the real request (DNS rebinding),
 *  - redirects are followed manually and re-checked, and never to another site,
 *  - a request budget and timeouts keep scans polite.
 */
class SafeHttp
{
    private int $requestCount = 0;

    private bool $hasSentRequest = false;

    public function __construct(private DnsResolver $dns)
    {
        //
    }

    public function get(string $url, array $headers = []): Response
    {
        return $this->send('GET', $url, $headers);
    }

    /**
     * GET a URL and follow up to $maxRedirects redirects on the same site.
     *
     * @return array{0: Response, 1: string} the final response and its URL
     */
    public function getFollowingRedirects(string $url, int $maxRedirects = 3): array
    {
        $current = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $response = $this->send('GET', $current);

            if (! $response->redirect()) {
                return [$response, $current];
            }

            $location = $response->header('Location');

            if ($location === '') {
                return [$response, $current];
            }

            $next = $this->resolveLocation($current, $location);

            if (! $this->isSameSite($current, $next)) {
                return [$response, $current];
            }

            $current = $next;
        }

        return [$response, $current];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function send(string $method, string $url, array $headers = []): Response
    {
        $target = $this->parseUrl($url);
        $ips = $this->assertHostAllowed($target['host']);

        $this->consumeBudget();
        $this->politeDelay();

        $options = [];

        if (filter_var($target['host'], FILTER_VALIDATE_IP) === false && defined('CURLOPT_RESOLVE')) {
            $ip = $ips[0];
            $pinned = str_contains($ip, ':') ? "[{$ip}]" : $ip;
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$pinned}"]];
        }

        return Http::withHeaders(array_merge([
            'User-Agent' => (string) config('leakwatch.http.user_agent'),
            'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
        ], $headers))
            ->timeout((int) config('leakwatch.http.timeout', 10))
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->withOptions($options)
            ->send($method, $url);
    }

    /**
     * Resolve a host and make sure every address is public.
     *
     * @return array<int, string> the allowed IPs
     *
     * @throws BlockedTargetException
     */
    public function assertHostAllowed(string $host): array
    {
        $host = strtolower(trim($host, '[]'));

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->dns->resolve($host);

        if ($ips === []) {
            throw BlockedTargetException::unresolvable($host);
        }

        if (! $this->privateTargetsAllowed()) {
            foreach ($ips as $ip) {
                if (IpGuard::isBlocked($ip)) {
                    throw BlockedTargetException::privateAddress($host, $ip);
                }
            }
        }

        return array_values($ips);
    }

    /**
     * Private targets can only be scanned in local development, and only when
     * explicitly switched on (for the bundled demo app).
     */
    public function privateTargetsAllowed(): bool
    {
        return (bool) config('leakwatch.allow_private_targets') && app()->environment('local');
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    public function resetBudget(): void
    {
        $this->requestCount = 0;
    }

    /**
     * @return array{scheme: string, host: string, port: int}
     *
     * @throws BlockedTargetException
     */
    public function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw BlockedTargetException::invalidUrl($url, 'not a valid absolute URL');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw BlockedTargetException::invalidUrl($url, 'only http and https are allowed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw BlockedTargetException::invalidUrl($url, 'credentials in URLs are not allowed');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, (array) config('leakwatch.http.allowed_ports', [80, 443]), true)) {
            throw BlockedTargetException::invalidUrl($url, "port {$port} is not allowed");
        }

        return [
            'scheme' => $scheme,
            'host' => strtolower(trim($parts['host'], '[]')),
            'port' => $port,
        ];
    }

    public function resolveLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(substr($path, 0, (int) strrpos($path, '/') + 1), '/');

        return $origin.$directory.'/'.$location;
    }

    /**
     * Redirects may change scheme (http -> https) or add/remove "www.", but
     * never send us to a different site.
     */
    public function isSameSite(string $from, string $to): bool
    {
        $strip = fn (string $url) => preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return $strip($from) !== '' && $strip($from) === $strip($to);
    }

    private function consumeBudget(): void
    {
        $limit = (int) config('leakwatch.http.max_requests_per_check', 15);

        if ($this->requestCount >= $limit) {
            throw new RequestBudgetExceededException("Request budget of {$limit} exceeded.");
        }

        $this->requestCount++;
    }

    private function politeDelay(): void
    {
        $delay = (int) config('leakwatch.http.delay_ms', 0);

        if ($this->hasSentRequest && $delay > 0) {
            usleep($delay * 1000);
        }

        $this->hasSentRequest = true;
    }
}
