<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;

class PlatformConfiguration
{
    const FAKE_VERSION = 'dev-master';

    protected VersionParser $versionParser;

    public function __construct(protected PackageInterface $package)
    {
        $this->versionParser = new VersionParser();
    }

    /**
     * @return PlatformPackage[]
     */
    public static function parse(PackageInterface $package): array
    {
        $platformPackages = [];
        $extras = $package->getExtra();

        $platformPackageConfigs = $extras['platform-packages'] ?? [];

        foreach ($platformPackageConfigs as $name => $config) {
            $config = self::validatePackageConfig($package, $name, $config);

            $platformPackages[] = new PlatformPackage($package, $name, $config);
        }

        return $platformPackages;
    }

    /**
     * Validate individual library configuration
     *
     * @param string $packageName
     * @param array $packageConfig
     *
     * @return array
     */
    private static function validatePackageConfig(PackageInterface $parent, string $packageName, array $packageConfig): array
    {
        $versionParser = new VersionParser();

        if (isset($packageConfig['version'])) {
            $packageConfig['prettyVersion'] = $packageConfig['version'];
            $packageConfig['version'] = $versionParser->normalize($packageConfig['version']);
        } elseif ($parent instanceof RootPackageInterface) {
            $packageConfig['prettyVersion'] = self::FAKE_VERSION;
            $packageConfig['version'] = $versionParser->normalize(self::FAKE_VERSION);
        } else {
            $packageConfig['version'] = $parent->getVersion();
            $packageConfig['prettyVersion'] = $parent->getPrettyVersion();
        }

        if (!isset($packageConfig['platforms']) || !is_array($packageConfig['platforms'])) {
            throw new \InvalidArgumentException("Invalid or missing platforms for library: $packageName");
        }

        $validatedPlatforms = [];
        foreach ($packageConfig['platforms'] as $platform => $url) {
            $urls = is_string($url) ? [$url] : $url;

            $processedUrls = array_map(function ($u) use ($packageConfig) {
                $processedUrl = str_replace('{version}', $packageConfig['prettyVersion'], $u);

                if (!filter_var($processedUrl, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException("Invalid URL: $processedUrl");
                }
                return $processedUrl;
            }, $urls);

            $validatedPlatforms[strtolower($platform)] = $processedUrls;
        }

        $packageConfig['platforms'] = $validatedPlatforms;

        return $packageConfig;
    }
}
