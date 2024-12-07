<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\InstalledVersions;
use Composer\Installer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProviderCapability
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

    /**
     * Deactivate the plugin
     */
    public function deactivate(Composer $composer, IOInterface $io): void {}

    /**
     * Uninstall the plugin
     */
    public function uninstall(Composer $composer, IOInterface $io) {}

    /**
     * Subscribe to Composer events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCommand',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallCommand',
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'onPostPackageUninstall',
        ];
    }

    public function onPostInstallCommand(Event $event): void
    {
        $rootPackage = $this->composer->getPackage();
        $this->processPlatformPackages($rootPackage);

        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepository->getCanonicalPackages() as $package) {
            $this->processPlatformPackages($package);
        }
    }

    /**
     * Handle package installation
     */
    public function onPostPackageInstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();
        $this->processPlatformPackages($package);
    }

    /**
     * Handle package update
     */
    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $package = $event->getOperation()->getTargetPackage();
        $this->processPlatformPackages($package);
    }

    /**
     * Handle package uninstall
     */
    public function onPostPackageUninstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();
        $this->uninstallPlatformPackages($package);

    }


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

    /**
     * Process platform-specific installers for a package
     */
    protected function processPlatformPackages(PackageInterface $package): void
    {
        $platformPackages = PlatformConfiguration::parse($package);

        $installationManager = $this->composer->getInstallationManager();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        $operations = array_map(function ($platformPackage) use ($localRepository) {
            $packageName = $platformPackage->getName();

            if (InstalledVersions::isInstalled($packageName)) {
                $currentVersion = InstalledVersions::getVersion($packageName);
                $currentPrettyVersion = InstalledVersions::getPrettyVersion($packageName);

                if ($currentVersion === $platformPackage->getVersion()) {
                    return null;
                }

                $existingPackage = clone $platformPackage;
                $existingPackage->replaceVersion($currentVersion, $currentPrettyVersion);
                return new UpdateOperation($existingPackage, $platformPackage);
            }

            return new InstallOperation($platformPackage);
        }, $platformPackages);

        $validOperations = array_filter($operations);
        if (empty($validOperations)) return;

        try {
            $installationManager->execute($localRepository, $validOperations);
            $this->io->write("Installed platform packages successfully");
        } catch (\Throwable $e) {
            $this->io->writeError("Failed to install platform packages: {$e->getMessage()}");
            throw $e;
        }

        $processedPackages = array_map(fn ($operation) => $operation->getPackage(), $validOperations);

        $this->updatePackageComposerJson($package, $processedPackages);
    }

    protected function uninstallPlatformPackages(PackageInterface $package): void
    {
        $platformPackages = PlatformConfiguration::parse($package);

        $installationManager = $this->composer->getInstallationManager();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        $operations = array_map(
            fn ($platformPackage) => InstalledVersions::isInstalled($platformPackage->getName()) ? new UninstallOperation($platformPackage) : null,
            $platformPackages
        );

        $validOperations = array_filter($operations);
        if (empty($validOperations)) return;

        try {
            $installationManager->execute($localRepository, $validOperations);

            $this->io->write("Uninstalled platform-specific packages successfully");
        } catch (\Throwable $e) {
            $this->io->writeError("Failed to uninstall platform packages: {$e->getMessage()}");
            throw $e;
        }

        $processedPackages = array_map(fn ($operation) => $operation->getPackage(), $validOperations);

        $this->updatePackageComposerJson($package, $processedPackages, false);
    }

    protected function updatePackageComposerJson(PackageInterface $package, array $platformPackages, bool $add = true): void
    {
        if (empty($platformPackages)) return;

        $installationManager = $this->composer->getInstallationManager();
        $packagePath = $installationManager->getInstallPath($package);
        $jsonPath = rtrim($packagePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        if (!file_exists($jsonPath)) {
            file_put_contents($jsonPath, '{}');
        }

        $contents = file_get_contents($jsonPath);
        $manipulator = new JsonManipulator($contents);

        foreach ($platformPackages as $platformPackage) {
            $packageName = $platformPackage->getName();
            $packageVersion = $platformPackage->getPrettyVersion();

            if ($add) {
                $manipulator->addLink('require', $packageName, $packageVersion, true);
            } else {
                $manipulator->removeSubNode('require', $packageName);
            }
        }

        file_put_contents($jsonPath, $manipulator->getContents());
        $this->composer->getLocker()->updateHash(new JsonFile($jsonPath));
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

    /**
     * Provide additional Composer commands
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => self::class
        ];
    }

    /**
     * Provide platform installer specific commands
     *
     * @return BaseCommand[]
     */
    public function getCommands(): array
    {
        return [
            new class extends BaseCommand
            {
                protected function configure(): void
                {
                    $this->setName('platform-packages:list')
                        ->setDescription('List the platform packages installed');
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    return 0;
                }
            }
        ];
    }
}
