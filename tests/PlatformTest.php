<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages\Tests;

use Codewithkyrian\ComposerPlatformPackages\Platform;

it('normalizes architecture names correctly', function () {
    expect(Platform::normalizeArchitecture('x86_64'))->toBe('x86_64')
        ->and(Platform::normalizeArchitecture('amd64'))->toBe('x86_64')
        ->and(Platform::normalizeArchitecture('aarch64'))->toBe('arm64')
        ->and(Platform::normalizeArchitecture('armv7'))->toBe('arm');
});

it('matches platforms correctly', function () {
    $macArm64 = ['os' => 'darwin', 'arch' => 'arm64', 'full' => 'Darwin Macbook Pro ARM64'];
    $linuxX64 = ['os' => 'linux', 'arch' => 'x86_64', 'full' => 'Linux Ubuntu x86_64'];
    $windowsX32 = ['os' => 'windows', 'arch' => 'x86', 'full' => 'Windows 10 x86'];

    // Test various platform matches
    expect(Platform::matches('all', $macArm64))->toBeTrue()
        ->and(Platform::matches('darwin', $macArm64))->toBeTrue()
        ->and(Platform::matches('darwin-arm64', $macArm64))->toBeTrue()
        ->and(Platform::matches('darwin-x86_64', $macArm64))->toBeFalse()
        ->and(Platform::matches('linux-x86_64', $linuxX64))->toBeTrue()
        ->and(Platform::matches('linux-arm64', $linuxX64))->toBeFalse()
        ->and(Platform::matches('win', $windowsX32))->toBeTrue()
        ->and(Platform::matches('win-32', $windowsX32))->toBeTrue()
        ->and(Platform::matches('win-64', $windowsX32))->toBeFalse();
});

it('finds the most appropriate URL for the current platform', function () {
    $platformUrls = [
        'darwin-arm64' => 'https://example.com/darwin-arm64',
        'darwin-x86_64' => 'https://example.com/darwin-x86_64',
        'linux-x86_64' => 'https://example.com/linux-x86_64',
        'win-32' => 'https://example.com/win-32',
        'win-64' => 'https://example.com/win-64',
        'win' => 'https://example.com/win',
        'all' => 'https://example.com/all'
    ];

    $macArm64 = ['os' => 'darwin', 'arch' => 'arm64', 'full' => 'Darwin Macbook Pro ARM64'];
    $linuxX64 = ['os' => 'linux', 'arch' => 'x86_64', 'full' => 'Linux Ubuntu x86_64'];
    $windowsX32 = ['os' => 'windows', 'arch' => 'x86', 'full' => 'Windows 10 x86'];

    expect(Platform::findMatchingUrl($platformUrls, $macArm64))->toBe('https://example.com/darwin-arm64')
        ->and(Platform::findMatchingUrl($platformUrls, $linuxX64))->toBe('https://example.com/linux-x86_64')
        ->and(Platform::findMatchingUrl($platformUrls, $windowsX32))->toBe('https://example.com/win-32');
});
