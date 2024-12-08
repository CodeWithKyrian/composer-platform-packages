<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages\Tests;

use Codewithkyrian\ComposerPlatformPackages\PlatformConfiguration;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use InvalidArgumentException;

beforeEach(function () {
    $this->package = new Package('organization/package', '1.0.0', '1.0.0');
    $this->rootPackage = new RootPackage('organization/root-package', '2.0.0', '2.0.0');
});

it('validates package name format correctly', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'valid-vendor/valid-package' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => 'https://example.com/package-{version}.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getName())->toBe('valid-vendor/valid-package');
});

it('throws exception for invalid package name format', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'invalid-package-name' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => 'https://example.com/package-{version}.zip'
                ]
            ]
        ]
    ]);

    PlatformConfiguration::parse($package);
})->throws(InvalidArgumentException::class, 'Invalid package name format');

it('throws exception for package name with uppercase characters', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'Vendor/Package-Name' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => 'https://example.com/package-{version}.zip'
                ]
            ]
        ]
    ]);

    PlatformConfiguration::parse($package);
})->throws(InvalidArgumentException::class, 'Invalid package name format');

it('validates complex but valid package names', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'vendor-with-numbers-123/package-with-numbers-456' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => 'https://example.com/package-{version}.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getName())->toBe('vendor-with-numbers-123/package-with-numbers-456');
});

it('uses provided version in package configuration', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-lib' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => 'https://example.com/mac-{version}.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getVersion())->toBe('1.2.3.0')
        ->and($parsedPackages[0]->getPrettyVersion())->toBe('1.2.3')
        ->and($parsedPackages[0]->getDistUrl())->toBe('https://example.com/mac-1.2.3.zip');
});

it('uses root package version when no version is provided', function () {
    $package = $this->rootPackage;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-lib' => [
                'platforms' => [
                    php_uname('s') => 'https://example.com/mac-{version}.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getVersion())->toBe('dev-master')
        ->and($parsedPackages[0]->getPrettyVersion())->toBe('dev-master')
        ->and($parsedPackages[0]->getDistUrl())->toBe('https://example.com/mac-dev-master.zip');

});

it('uses package version when no version is provided for non-root package', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-lib' => [
                'platforms' => [
                    php_uname('s') => 'https://example.com/mac-{version}.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getVersion())->toBe('1.0.0')
        ->and($parsedPackages[0]->getPrettyVersion())->toBe('1.0.0')
        ->and($parsedPackages[0]->getDistUrl())->toBe('https://example.com/mac-1.0.0.zip');
});

it('uses archive type when specified', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-tool' => [
                'type' => 'tar',
                'platforms' => [
                    php_uname('s') => 'https://example.com/mac.tar'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages[0]->getDistType())->toBe('tar');
});

it('infers archive type when not specified, but is in the url', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-tool' => [
                'platforms' => [
                    php_uname('s') => 'https://example.com/mac.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages[0]->getDistType())->toBe('zip');
});

it('throws exception when no platforms are defined', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-lib' => []
        ]
    ]);

    expect(fn () => PlatformConfiguration::parse($package))
        ->toThrow(InvalidArgumentException::class, 'Invalid or missing platforms');
});

it('supports all platforms configuration', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'organization/test-lib' => [
                'platforms' => [
                    'all' => 'https://example.com/generic.zip'
                ]
            ]
        ]
    ]);

    $parsedPackages = PlatformConfiguration::parse($package);

    expect($parsedPackages)->toHaveCount(1)
        ->and($parsedPackages[0]->getDistUrl())->toBe('https://example.com/generic.zip');
});
