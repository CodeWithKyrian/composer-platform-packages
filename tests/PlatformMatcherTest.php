<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages\Tests;

use Codewithkyrian\ComposerPlatformPackages\PlatformMatcher;

it('normalizes architecture names correctly', function () {
    expect(PlatformMatcher::normalizeArchitecture('x86_64'))->toBe('x86_64')
        ->and(PlatformMatcher::normalizeArchitecture('amd64'))->toBe('x86_64')
        ->and(PlatformMatcher::normalizeArchitecture('aarch64'))->toBe('arm64')
        ->and(PlatformMatcher::normalizeArchitecture('armv7'))->toBe('arm');
});

it('matches platforms correctly', function () {
    $macArm64 = [
        'os' => 'darwin',
        'arch' => 'arm64',
        'full' => 'Darwin Macbook Pro ARM64'
    ];

    $linuxX64 = [
        'os' => 'linux',
        'arch' => 'x86_64',
        'full' => 'Linux Ubuntu x86_64'
    ];

    $windowsX32 = [
        'os' => 'windows',
        'arch' => 'x86',
        'full' => 'Windows 10 x86'
    ];

    // Test various platform matches
    expect(PlatformMatcher::platformMatches('all', $macArm64))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('darwin', $macArm64))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('darwin-arm64', $macArm64))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('darwin-x86_64', $macArm64))->toBeFalse()
        ->and(PlatformMatcher::platformMatches('linux-x86_64', $linuxX64))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('linux-arm64', $linuxX64))->toBeFalse()
        ->and(PlatformMatcher::platformMatches('win', $windowsX32))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('win-32', $windowsX32))->toBeTrue()
        ->and(PlatformMatcher::platformMatches('win-64', $windowsX32))->toBeFalse();
});
