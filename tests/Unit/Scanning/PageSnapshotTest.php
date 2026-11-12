<?php

use App\Scanning\Checks\EnvExposedCheck;
use App\Scanning\Data\PageSnapshot;

test('set-cookie headers are parsed into flags', function () {
    $snapshot = new PageSnapshot('https://x.test', 200, [
        'set-cookie' => [
            'XSRF-TOKEN=abc; expires=Fri, 01 Jan 2027 00:00:00 GMT; Max-Age=7200; path=/; secure; samesite=lax',
            'app_session=def; path=/; httponly',
        ],
    ], 'Home', 10, 'hash');

    expect($snapshot->cookies())->toBe([
        ['name' => 'XSRF-TOKEN', 'secure' => true, 'httponly' => false, 'samesite' => 'lax'],
        ['name' => 'app_session', 'secure' => false, 'httponly' => true, 'samesite' => null],
    ]);
});

test('body hash ignores csrf tokens so identical pages compare equal', function () {
    $a = '<html><meta name="csrf-token" content="aaa"><title>Home</title></html>';
    $b = '<html><meta name="csrf-token" content="bbb"><title>Home</title></html>';

    expect(PageSnapshot::hashBody($a))->toBe(PageSnapshot::hashBody($b));
});

test('only env variable names are extracted, never values', function () {
    $names = EnvExposedCheck::variableNames("APP_KEY=base64:secret\nDB_PASSWORD=hunter2\n# comment\nexport MAIL_HOST=smtp\nlowercase=no");

    expect($names)->toBe(['APP_KEY', 'DB_PASSWORD', 'MAIL_HOST']);
});
