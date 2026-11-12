<?php

use App\Scanning\Net\IpGuard;

test('private, loopback and metadata addresses are blocked', function (string $ip) {
    expect(IpGuard::isBlocked($ip))->toBeTrue();
})->with([
    '127.0.0.1', '127.8.8.8', '10.1.2.3', '172.16.0.1', '172.31.255.255', '192.168.1.10',
    '169.254.169.254', '100.64.0.1', '0.0.0.0', '224.0.0.1', '255.255.255.255',
    '::1', '::', 'fc00::1', 'fd12:3456::1', 'fe80::1', '::ffff:127.0.0.1', '::ffff:10.0.0.5',
    'not-an-ip', '',
]);

test('public addresses are allowed', function (string $ip) {
    expect(IpGuard::isBlocked($ip))->toBeFalse();
})->with([
    '93.184.216.34', '8.8.8.8', '1.1.1.1', '172.32.0.1', '2606:4700:4700::1111', '::ffff:93.184.216.34',
]);
