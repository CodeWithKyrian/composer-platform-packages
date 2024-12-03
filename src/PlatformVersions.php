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
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     *
     * @return bool
     */
    public static function isInstalled(string $parentPackage, string $platformPackage): bool
    {
        $fullPackageName = self::getFullPackageName($parentPackage, $platformPackage);
        return InstalledVersions::isInstalled($fullPackageName);
    }

    /**
     * Get the version of a platform-specific package
     *
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getVersion(string $parentPackage, string $platformPackage): ?string
    {
        $fullPackageName = self::getFullPackageName($parentPackage, $platformPackage);
        return InstalledVersions::getVersion($fullPackageName);
    }

    /**
     * Get the pretty version of a platform-specific package
     *
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getPrettyVersion(string $parentPackage, string $platformPackage): ?string
    {
        $fullPackageName = self::getFullPackageName($parentPackage, $platformPackage);
        return InstalledVersions::getPrettyVersion($fullPackageName);
    }

    /**
     * Get the installation path of a platform-specific package
     *
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     *
     * @return string|null
     */
    public static function getInstallPath(string $parentPackage, string $platformPackage): ?string
    {
        $fullPackageName = self::getFullPackageName($parentPackage, $platformPackage);
        return Platform::realpath(InstalledVersions::getInstallPath($fullPackageName));
    }

    /**
     * Check if a platform-specific package satisfies a version constraint
     *
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     * @param string $versionConstraint Version constraint to check
     *
     * @return bool
     */
    public static function satisfies(string $parentPackage, string $platformPackage, string $versionConstraint): bool
    {
        $fullPackageName = self::getFullPackageName($parentPackage, $platformPackage);
        $versionParser = new VersionParser();
        return InstalledVersions::satisfies($versionParser, $fullPackageName, $versionConstraint);
    }

    /**
     * Generate the full package name for a platform-specific package
     *
     * @param string $parentPackage Parent package name
     * @param string $platformPackage Platform-specific package name
     *
     * @return string
     */
    public static function getFullPackageName(string $parentPackage, string $platformPackage): string
    {
        [$parentOrg, $parentName] = explode('/', $parentPackage);
        return "$parentOrg/$parentName:$platformPackage";
    }
}
