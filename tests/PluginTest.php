<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages\Tests;

use Codewithkyrian\ComposerPlatformPackages\PlatformVersions;
use Codewithkyrian\ComposerPlatformPackages\Plugin;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\NullIO;
use Composer\Package\Package;

beforeEach(function () {
    $this->package = new Package('organization/package', '1.0.0', '1.0.0');
    $this->testZipFile = sprintf("file://%s", realpath(__DIR__.'/fixtures/test-package.zip'));
    $this->testTarFile = sprintf("file://%s", realpath(__DIR__.'/fixtures/test-package.tar.gz'));
});

it('correctly installs and uninstalls a platform package', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'test-lib' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => $this->testZipFile
                ]
            ]
        ]
    ]);

    $io = new NullIO();
    $composer = (new Factory())->createComposer($io);

    $plugin = new Plugin();
    $plugin->activate($composer, $io);

    $event = $this->createMock(PackageEvent::class);
    $operation = $this->createMock(InstallOperation::class);
    $operation->method('getPackage')->willReturn($package);
    $event->method('getOperation')->willReturn($operation);

    $plugin->onPostPackageInstall($event);

    expect(PlatformVersions::isInstalled($package->getName(), 'test-lib'))->toBeTrue()
        ->and(PlatformVersions::getInstallPath($package->getName(), 'test-lib'))->toBeDirectory();

    // Now uninstall
    $uninstallEvent = $this->createMock(PackageEvent::class);
    $uninstallOperation = $this->createMock(UninstallOperation::class);
    $uninstallOperation->method('getPackage')->willReturn($package);
    $uninstallEvent->method('getOperation')->willReturn($uninstallOperation);
    $plugin->onPostPackageUninstall($uninstallEvent);

    expect(PlatformVersions::isInstalled($package->getName(), 'test-lib'))->toBeFalse();
});

it('correctly installs and uninstalls multiple platform packages', function () {
    $package = $this->package;
    $package->setExtra([
        'platform-packages' => [
            'test-lib' => [
                'version' => '1.2.3',
                'platforms' => [
                    php_uname('s') => $this->testZipFile
                ]
            ],
            'test-lib2' => [
                'version' => '1.0.0',
                'platforms' => [
                    php_uname('s') => $this->testTarFile
                ]
            ],
        ]
    ]);

    $io = new NullIO();
    $composer = (new Factory())->createComposer($io);

    $plugin = new Plugin();
    $plugin->activate($composer, $io);

    $event = $this->createMock(PackageEvent::class);
    $operation = $this->createMock(InstallOperation::class);
    $operation->method('getPackage')->willReturn($package);
    $event->method('getOperation')->willReturn($operation);

    $plugin->onPostPackageInstall($event);

    expect(PlatformVersions::isInstalled($package->getName(), 'test-lib'))->toBeTrue()
        ->and(PlatformVersions::isInstalled($package->getName(), 'test-lib2'))->toBeTrue()
        ->and(PlatformVersions::getInstallPath($package->getName(), 'test-lib'))->toBeDirectory()
        ->and(PlatformVersions::getInstallPath($package->getName(), 'test-lib2'))->toBeDirectory();

    // Now uninstall
    $uninstallEvent = $this->createMock(PackageEvent::class);
    $uninstallOperation = $this->createMock(UninstallOperation::class);
    $uninstallOperation->method('getPackage')->willReturn($package);
    $uninstallEvent->method('getOperation')->willReturn($uninstallOperation);
    $plugin->onPostPackageUninstall($uninstallEvent);

    expect(PlatformVersions::isInstalled($package->getName(), 'test-lib'))->toBeFalse()
        ->and(PlatformVersions::isInstalled($package->getName(), 'test-lib2'))->toBeFalse();
});

it('provides custom commands', function () {
    $plugin = new Plugin();
    $commands = $plugin->getCommands();

    expect($commands)->toBeArray()
        ->and(count($commands))->toBeGreaterThan(0);
});
