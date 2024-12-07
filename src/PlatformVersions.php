<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Composer\Util\Platform;

class PlatformVersions
{
    /**
     * Check if a platform-specific package is installed
     *
     * @param string $platformPackage Platform-specific package name
     *
     * @return bool
     */
    public static function isInstalled(string $platformPackage): bool
    {
        return InstalledVersions::isInstalled($platformPackage);
    }

    /**
     * Get the version of a platform-specific package
     *
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getVersion(string $platformPackage): ?string
    {
        return InstalledVersions::getVersion($platformPackage);
    }

    /**
     * Get the pretty version of a platform-specific package
     *
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getPrettyVersion(string $platformPackage): ?string
    {
        return InstalledVersions::getPrettyVersion($platformPackage);
    }

    /**
     * Get the installation path of a platform-specific package
     *
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getInstallPath(string $platformPackage): ?string
    {
        return realpath(InstalledVersions::getInstallPath($platformPackage));
    }

    /**
     * Check if a platform-specific package satisfies a version constraint
     *
     * @param string $platformPackage Platform-specific package name
     * @param string $versionConstraint Version constraint to check
     *
     * @return bool
     */
    public static function satisfies(string $platformPackage, string $versionConstraint): bool
    {
        $versionParser = new VersionParser();
        return InstalledVersions::satisfies($versionParser, $platformPackage, $versionConstraint);
    }
}
