<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

class PlatformMatcher
{
    /**
     * Get detailed current platform information
     */
    public static function getCurrentPlatform(): array
    {
        $osName = strtolower(php_uname('s'));
        $arch = php_uname('m');

        $normalizedArch = self::normalizeArchitecture($arch);

        return [
            'os' => $osName,
            'arch' => $normalizedArch,
            'full' => php_uname()
        ];
    }

    /**
     * Normalize architecture names for consistent matching
     *
     * @param string $arch
     */
    public static function normalizeArchitecture(string $arch): string
    {
        $arch = strtolower($arch);

        // Common architecture normalization
        $archMap = [
            // x86 family
            'x86_64' => 'x86_64',
            'amd64' => 'x86_64',
            'i386' => 'x86',
            'i686' => 'x86',
            'x64' => 'x86_64',
            'x86' => 'x86',
            '32' => 'x86',

            // ARM family
            'arm64' => 'arm64',
            'aarch64' => 'arm64',
            'armv7' => 'arm',
            'armv8' => 'arm64',
            'arm64v8' => 'arm64',

            // Other architectures
            'ppc64' => 'ppc64',
            'ppc64le' => 'ppc64le',
            's390x' => 's390x'
        ];

        return $archMap[$arch] ?? $arch;
    }

    /**
     * Check if a platform definition matches the current platform
     *
     * @param string $definedPlatform
     * @param ?array $currentPlatform
     *
     * @return bool
     */
    public static function platformMatches(string $definedPlatform, ?array $currentPlatform = null): bool
    {
        $currentPlatform = $currentPlatform ?? self::getCurrentPlatform();
        $definedPlatform = strtolower($definedPlatform);

        // Exact match for all platforms
        if ($definedPlatform === 'all') {
            return true;
        }

        // Split platform into OS and optional architecture
        $parts = explode('-', $definedPlatform);
        $os = $parts[0];
        $arch = count($parts) > 1 ? $parts[1] : null;

        // OS matching with flexible mapping
        $osMatch = self::matchOperatingSystem($os, $currentPlatform['os']);

        // Architecture matching (if specified)
        $archMatch = $arch === null ||
            self::normalizeArchitecture($arch) === $currentPlatform['arch'];

        return $osMatch && $archMatch;
    }

    /**
     * More flexible OS matching
     *
     * @param string $definedOs
     * @param string $currentOs
     *
     * @return bool
     */
    private static function matchOperatingSystem(string $definedOs, string $currentOs): bool
    {
        $osAliases = [
            'win' => ['windows', 'win32', 'win64'],
            'darwin' => ['macos', 'mac', 'darwin'],
            'linux' => ['linux', 'gnu/linux'],
            'raspberrypi' => ['raspbian', 'raspberry pi']
        ];

        // Exact match
        if ($definedOs === $currentOs) {
            return true;
        }

        // Check aliases
        foreach ($osAliases as $alias => $variations) {
            if ($definedOs === $alias && in_array($currentOs, $variations)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the most appropriate URL for the current platform
     *
     * @param array $platformUrls
     * @param array|null $currentPlatform
     *
     * @throws \Exception
     */
    public static function findMatchingPlatformUrl(array $platformUrls, array $currentPlatform = null): string
    {
        // Use current platform if not provided
        $currentPlatform = $currentPlatform ?? self::getCurrentPlatform();

        // First, try exact platform match
        $exactPlatformStr = "{$currentPlatform['os']}_{$currentPlatform['arch']}";
        if (isset($platformUrls[$exactPlatformStr])) {
            return is_array($platformUrls[$exactPlatformStr])
                ? $platformUrls[$exactPlatformStr][0]
                : $platformUrls[$exactPlatformStr];
        }

        // Try OS-only match
        if (isset($platformUrls[$currentPlatform['os']])) {
            return is_array($platformUrls[$currentPlatform['os']])
                ? $platformUrls[$currentPlatform['os']][0]
                : $platformUrls[$currentPlatform['os']];
        }

        // Check for 'all' platform
        if (isset($platformUrls['all'])) {
            return is_array($platformUrls['all'])
                ? $platformUrls['all'][0]
                : $platformUrls['all'];
        }

        throw new \Exception('No valid url could be found for this platform');
    }
}
