<?php

use App\Dependencies\VersionConstraint;

test('version ranges from packagist advisories are matched correctly', function (string $version, string $constraint, bool $expected) {
    expect(VersionConstraint::matches($version, $constraint))->toBe($expected);
})->with([
    'inside simple range' => ['1.2.2', '>=1.0.0,<1.2.3', true],
    'upper bound is exclusive' => ['1.2.3', '>=1.0.0,<1.2.3', false],
    'v prefix' => ['v1.2.2', '>=1.0.0,<1.2.3', true],
    'second alternative' => ['2.0.3', '>=1.0.0,<1.2.3|>=2.0.0,<2.0.4', true],
    'fixed in second branch' => ['2.0.4', '>=1.0.0,<1.2.3|>=2.0.0,<2.0.4', false],
    'below all ranges' => ['0.9.9', '>=1.0.0,<1.2.3|>=2.0.0,<2.0.4', false],
    'laravel style range' => ['11.30.0', '<10.48.23|>=11.0.0,<11.31.0', true],
    'laravel patched' => ['11.31.0', '<10.48.23|>=11.0.0,<11.31.0', false],
    'space separated and' => ['5.5.40', '>=5.5.40 <5.6.30', true],
    'dev branch never matches' => ['dev-main', '<2.0', false],
    'pre-release is lower' => ['1.0.0-beta1', '<1.0.0', true],
    'exact version' => ['3.2.1', '3.2.1', true],
    'short version padded' => ['8.0', '>=8.0.0,<8.0.1', true],
    'wildcard' => ['1.2.3', '*', true],
    'empty constraint' => ['1.2.3', '', false],
]);
