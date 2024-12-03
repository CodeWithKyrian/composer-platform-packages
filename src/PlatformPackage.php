<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Package\Package;
use Composer\Package\PackageInterface;

class PlatformPackage extends Package
{
    public function __construct(PackageInterface $parent, string $name, array $config)
    {
        $fullName = PlatformVersions::getFullPackageName($parent->getName(), $name);

        parent::__construct($fullName, $config['version'], $config['prettyVersion']);

        $matchingUrl = PlatformMatcher::findMatchingPlatformUrl($config['platforms']);

        $this->setType('library');
        $this->setInstallationSource('dist');
        $this->setDistUrl($matchingUrl);

        $this->setDistType($config['type'] ?? self::inferArchiveType($matchingUrl));
    }

    private static function inferArchiveType(string $url): string
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
