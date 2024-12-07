<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages\Tests;

use Composer\InstalledVersions;
use Composer\Package\Package;
use Composer\Util\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->package = new Package('organization/package', '1.0.0', '1.0.0');
    $this->testZipFile = sprintf("file://%s", realpath(__DIR__.'/fixtures/test-package.zip'));
    $this->testTarFile = sprintf("file://%s", realpath(__DIR__.'/fixtures/test-package.tar.gz'));
});

it('can install a platform package', function () {
    setupTestProject([
        'test-vendor/test-package' => [
            'version' => '1.0.0',
            'platforms' => [
                php_uname('s') => $this->testZipFile
            ]
        ]
    ]);

    $result = runComposerCommandInTestProject('require', [
        'packages' => ['test-vendor/test-package'],
        '--no-interaction' => true,
        '--no-progress' => true,
    ]);

    expect($result)->toBe(0)
        ->and(InstalledVersions::isInstalled('test-vendor/test-package'))->toBeTrue()
        ->and(InstalledVersions::getInstallPath('test-vendor/test-package'))->toBeDirectory();

    // Cleanup
    $result = runComposerCommandInTestProject('remove', [
        'packages' => ['test-vendor/test-package'],
        '--no-interaction' => true,
        '--no-audit' => true,
        '--no-progress' => true,
    ]);

    expect($result)->toBe(0)
        ->and(InstalledVersions::isInstalled('test-vendor/test-package'))->toBeFalse();
});
