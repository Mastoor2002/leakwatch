<?php

namespace Leakwatch\Agent;

use RuntimeException;

/**
 * Reads composer.lock and returns only package names and versions.
 * Nothing else from the application (code, config, .env) is ever read or sent.
 */
class LockfileReader
{
    /**
     * @return array{lock_hash: string, packages: array<int, array{name: string, version: string, dev: bool}>}
     */
    public function read(string $path, bool $includeDev = true): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("composer.lock not found at {$path}");
        }

        $contents = (string) file_get_contents($path);
        $lock = json_decode($contents, true);

        if (! is_array($lock)) {
            throw new RuntimeException('composer.lock is not valid JSON.');
        }

        $packages = [];

        foreach (['packages' => false, 'packages-dev' => true] as $section => $isDev) {
            if ($isDev && ! $includeDev) {
                continue;
            }

            foreach ($lock[$section] ?? [] as $package) {
                if (isset($package['name'], $package['version'])) {
                    $packages[] = [
                        'name' => strtolower((string) $package['name']),
                        'version' => (string) $package['version'],
                        'dev' => $isDev,
                    ];
                }
            }
        }

        return [
            'lock_hash' => hash('sha256', $contents),
            'packages' => $packages,
        ];
    }
}
