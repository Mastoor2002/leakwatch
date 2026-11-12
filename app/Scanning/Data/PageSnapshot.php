<?php

namespace App\Scanning\Data;

use Illuminate\Http\Client\Response;

/**
 * A small, storable summary of an HTTP response (no full body).
 * Saved on the scan so later checks don't need to re-fetch the homepage.
 */
readonly class PageSnapshot
{
    /**
     * @param  array<string, array<int, string>>  $headers  lower-cased header name => values
     * @param  array<int, string>  $markers
     */
    public function __construct(
        public string $url,
        public int $status,
        public array $headers,
        public ?string $title,
        public int $length,
        public string $bodyHash,
        public array $markers = [],
    ) {
        //
    }

    public static function fromResponse(Response $response, string $url): self
    {
        $body = $response->body();

        return new self(
            url: $url,
            status: $response->status(),
            headers: self::normalizeHeaders($response->headers()),
            title: self::extractTitle($body),
            length: strlen($body),
            bodyHash: self::hashBody($body),
            markers: self::detectMarkers($body),
        );
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, array<int, string>>
     */
    public static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower((string) $name)] = array_map('strval', (array) $values);
        }

        return $normalized;
    }

    public static function extractTitle(string $body): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1])));
        }

        return null;
    }

    /**
     * Hash the body after removing values that change on every request
     * (CSRF tokens, nonces), so two identical pages produce the same hash.
     */
    public static function hashBody(string $body): string
    {
        $normalized = preg_replace([
            '/(name="csrf-token"\s+content=")[^"]*/i',
            '/(name="_token"\s+value=")[^"]*/i',
            '/(nonce=")[^"]*/i',
            '/\s+/',
        ], ['$1', '$1', '$1', ' '], $body);

        return sha1((string) $normalized);
    }

    /**
     * Framework hints found in the body. Only booleans are kept, never content.
     *
     * @return array<int, string>
     */
    public static function detectMarkers(string $body): array
    {
        $markers = [];

        $patterns = [
            'csrf_meta' => '/<meta\s+name="csrf-token"/i',
            'livewire' => '/livewire|wire:(?:model|click|submit)/i',
            'vite_build' => '#/build/assets/#',
            'inertia' => '/data-page="/i',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $body)) {
                $markers[] = $name;
            }
        }

        return $markers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function headerValues(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Parsed Set-Cookie headers.
     *
     * @return array<int, array{name: string, secure: bool, httponly: bool, samesite: string|null}>
     */
    public function cookies(): array
    {
        $cookies = [];

        foreach ($this->headerValues('set-cookie') as $raw) {
            $parts = array_map('trim', explode(';', $raw));
            $name = trim(strstr($parts[0], '=', true) ?: $parts[0]);
            $attributes = array_map('strtolower', array_slice($parts, 1));

            $sameSite = null;

            foreach ($attributes as $attribute) {
                if (str_starts_with($attribute, 'samesite=')) {
                    $sameSite = substr($attribute, 9);
                }
            }

            $cookies[] = [
                'name' => $name,
                'secure' => in_array('secure', $attributes, true),
                'httponly' => in_array('httponly', $attributes, true),
                'samesite' => $sameSite,
            ];
        }

        return $cookies;
    }

    public function hasMarker(string $marker): bool
    {
        return in_array($marker, $this->markers, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'status' => $this->status,
            'headers' => $this->headers,
            'title' => $this->title,
            'length' => $this->length,
            'body_hash' => $this->bodyHash,
            'markers' => $this->markers,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string) $data['url'],
            status: (int) $data['status'],
            headers: (array) $data['headers'],
            title: $data['title'] ?? null,
            length: (int) $data['length'],
            bodyHash: (string) $data['body_hash'],
            markers: (array) ($data['markers'] ?? []),
        );
    }
}
