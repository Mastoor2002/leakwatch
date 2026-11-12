<?php

namespace App\Scanning;

use App\Models\Scan;
use App\Models\Site;
use App\Scanning\Contracts\DnsResolver;
use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Data\PageSnapshot;
use App\Scanning\Exceptions\RequestBudgetExceededException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Everything a check needs: the site, the safe HTTP client, and snapshots of
 * the homepage and of a deliberately missing page (the "soft-404 baseline").
 *
 * Many sites answer 200 + their homepage for ANY path. Without the baseline,
 * "GET /.env returned 200" would be a false alarm, so checks always ask
 * isSoft404() before trusting a 200.
 */
class ScanContext
{
    /**
     * @var array<string, Response|null>
     */
    private array $responses = [];

    public function __construct(
        public readonly Site $site,
        public readonly SafeHttp $http,
        public readonly DnsResolver $dns,
        public readonly TlsInspector $tls,
        public readonly string $origin,
        public readonly ?PageSnapshot $homepage,
        public readonly ?PageSnapshot $baseline,
    ) {
        //
    }

    public static function fromScan(Scan $scan): self
    {
        $context = $scan->context ?? [];

        return new self(
            site: $scan->site,
            http: app(SafeHttp::class),
            dns: app(DnsResolver::class),
            tls: app(TlsInspector::class),
            origin: (string) ($context['origin'] ?? $scan->site->url),
            homepage: isset($context['homepage']) ? PageSnapshot::fromArray($context['homepage']) : null,
            baseline: isset($context['baseline']) ? PageSnapshot::fromArray($context['baseline']) : null,
        );
    }

    public function url(string $path): string
    {
        return rtrim($this->origin, '/').'/'.ltrim($path, '/');
    }

    public function host(): string
    {
        return strtolower((string) parse_url($this->origin, PHP_URL_HOST));
    }

    public function isHttps(): bool
    {
        return str_starts_with($this->origin, 'https://');
    }

    /**
     * Fetch a path on the site. Returns null on network errors so a single
     * unreachable URL never crashes a check. Responses are cached per scan step.
     *
     * @param  array<string, string>  $headers
     */
    public function fetch(string $path, string $method = 'GET', array $headers = []): ?Response
    {
        $key = $method.' '.$path;

        if (array_key_exists($key, $this->responses)) {
            return $this->responses[$key];
        }

        try {
            $response = $this->http->send($method, $this->url($path), $headers);
        } catch (RequestBudgetExceededException $e) {
            throw $e;
        } catch (Throwable) {
            $response = null;
        }

        return $this->responses[$key] = $response;
    }

    /**
     * True when the response looks like the site's generic "not found" or
     * catch-all page rather than real content at that path.
     */
    public function isSoft404(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $body = $response->body();
        $hash = PageSnapshot::hashBody($body);
        $title = PageSnapshot::extractTitle($body);

        foreach ([$this->baseline, $this->homepage] as $reference) {
            if ($reference === null || $reference->status < 200 || $reference->status >= 300) {
                continue;
            }

            if ($hash === $reference->bodyHash) {
                return true;
            }

            $similarLength = $reference->length > 0
                && abs(strlen($body) - $reference->length) / $reference->length < 0.1;

            if ($title !== null && $title === $reference->title && $similarLength) {
                return true;
            }
        }

        return false;
    }

    /**
     * A 2xx response that is not the catch-all page.
     */
    public function isRealHit(?Response $response): bool
    {
        return $response !== null && $response->successful() && ! $this->isSoft404($response);
    }

    public static function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/^\s*(<!doctype|<html|<head|<body|<\?xml)/i', $body);
    }
}
