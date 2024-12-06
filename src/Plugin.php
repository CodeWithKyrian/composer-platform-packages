<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\ArrayLoader;
use Composer\Plugin\PluginInterface;
use Composer\Repository\ArrayRepository;

class Plugin implements PluginInterface
{
    protected Composer $composer;

    protected IOInterface $io;

    protected string $cacheFile;

    /**
     * Activate the plugin
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->cacheFile = __DIR__.'/../platform-packages-cache.json';

        $platformPackages = $this->getAllPlatformPackages();
        if (!empty($platformPackages)) {
            $repositoryManager = $this->composer->getRepositoryManager();
            $repositoryManager->prependRepository(new ArrayRepository($platformPackages));
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io) {}

    private function getAllPlatformPackages(): array
    {
        if ($platformPackages = $this->getCachedPackages()) {
            return $platformPackages;
        }

        $rootPackage = $this->composer->getPackage();
        $platformPackages = PlatformConfiguration::parse($rootPackage);

        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepository->getCanonicalPackages() as $package) {
            $platformPackages = array_merge($platformPackages, PlatformConfiguration::parse($package));
        }

        $this->cachePackages($platformPackages);

        return $platformPackages;
    }

    private function cachePackages($packages): void
    {
        $dumper = new ArrayDumper();
        $packageData = array_map(fn ($package) => $dumper->dump($package), $packages);

        $composerFile = Factory::getComposerFile();
        $lockFile = Factory::getLockFile($composerFile);

        $cacheData = [
            'composer_json_signature' => $this->getSignature($composerFile),
            'composer_lock_signature' => $this->getSignature($lockFile),
            'packages' => $packageData
        ];

        file_put_contents($this->cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getCachedPackages(): bool|array
    {
        if (!file_exists($this->cacheFile)) return false;

        $cacheData = json_decode(file_get_contents($this->cacheFile), true);

        $composerFile = Factory::getComposerFile();
        $lockFile = Factory::getLockFile($composerFile);

        $currentComposerJsonSignature = $this->getSignature($composerFile);
        if ($cacheData['composer_json_signature'] !== $currentComposerJsonSignature) {
            return false;
        }

        $currentComposerLockSignature = $this->getSignature($lockFile);
        if ($cacheData['composer_lock_signature'] !== $currentComposerLockSignature) {
            return false;
        }

        $loader = new ArrayLoader();
        return array_map(fn ($packageData) => $loader->load($packageData), $cacheData['packages']);
    }

    private function getSignature(string $filePath): ?string
    {
        return file_exists($filePath)
            ? md5_file($filePath)
            : null;
    }
}
