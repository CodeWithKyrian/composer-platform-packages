<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use React\Promise\PromiseInterface;

class Installer extends LibraryInstaller
{
    public function __construct(IOInterface $io, PartialComposer $composer)
    {
        parent::__construct($io, $composer, "platform-package");
    }

    public function download(PackageInterface $package, ?PackageInterface $prevPackage = null): ?PromiseInterface
    {
        if ($url = $this->resolveDistUrl($package)) {
            $package->setDistUrl($url);
            $package->setDistType($this->inferArchiveType($url));
        }

        return parent::download($package, $prevPackage);
    }

    private function resolveDistUrl(PackageInterface $package): string|false
    {
        $platformUrls = $package->getExtra()['platform-urls'] ?? [];
        $platformUrls = $this->validatePlatformUrls($package, $platformUrls);

        if ($matchingUrl = Platform::findMatchingUrl($platformUrls)) {
            return $matchingUrl;
        }

        $this->io->writeError("{$package->getName()}: No download URL found for current platform");
        return false;
    }

    private function validatePlatformUrls(PackageInterface $package, array $platformUrls): array
    {
        $validatedPlatforms = [];
        foreach ($platformUrls as $platform => $urls) {
            $urls = is_string($urls) ? [$urls] : $urls;
            $processedUrls = array_map(function ($u) use ($package) {
                $processedUrl = str_replace('{version}', $package->getPrettyVersion(), $u);

                if (!filter_var($processedUrl, FILTER_VALIDATE_URL)) {
                    $this->io->writeError("{$package->getName()}: Invalid URL : $processedUrl. Skipping...");
                    return null;
                }

                return $processedUrl;
            }, $urls);

            $validatedPlatforms[strtolower($platform)] = array_filter($processedUrls);
        }

        return $validatedPlatforms;
    }

    private function inferArchiveType(string $url): string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        $archiveTypes = [
            // Compressed archives
            'zip' => 'zip',
            'tar' => 'tar',
            'gz' => 'tar',
            'tgz' => 'tar',
            'tbz2' => 'tar',
            'bz2' => 'tar',
            '7z' => '7z',
            'rar' => 'rar',

            // Less common but still valid
            'xz' => 'tar',
            'lz' => 'tar',
            'lzma' => 'tar',
        ];

        if (isset($archiveTypes[$extension])) {
            return $archiveTypes[$extension];
        }

        try {
            $headers = get_headers($url, true);

            if (is_array($headers)) {
                $contentType = strtolower($headers['Content-Type'] ?? '');

                // Common content type mappings
                $contentTypeMap = [
                    'application/zip' => 'zip',
                    'application/x-zip-compressed' => 'zip',
                    'application/x-tar' => 'tar',
                    'application/x-gzip' => 'tar',
                    'application/gzip' => 'tar',
                    'application/x-bzip2' => 'tar',
                ];

                foreach ($contentTypeMap as $type => $archiveType) {
                    if (strpos($contentType, $type) !== false) {
                        return $archiveType;
                    }
                }
            }
        } catch (\Exception) {
        }

        // Fallback to ZIP if no other type could be determined
        return 'zip';
    }
}
