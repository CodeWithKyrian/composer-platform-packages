<?php

declare(strict_types=1);

namespace Codewithkyrian\ComposerPlatformPackages;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\InstalledVersions;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProviderCapability
{
    protected Composer $composer;

    protected IOInterface $io;

    /**
     * Activate the plugin
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
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
            $platformPackages = $this->processPlatformPackages($package);

            $this->updatePackageComposerJson($package, $platformPackages);
        }
    }

    /**
     * Handle package installation
     */
    public function onPostPackageInstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();
        $platformPackages = $this->processPlatformPackages($package);

        $this->updatePackageComposerJson($package, $platformPackages);
    }

    /**
     * Handle package update
     */
    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $package = $event->getOperation()->getTargetPackage();
        $platformPackages = $this->processPlatformPackages($package);

        $this->updatePackageComposerJson($package, $platformPackages);
    }

    /**
     * Handle package uninstall
     */
    public function onPostPackageUninstall(PackageEvent $event): void
    {
        $package = $event->getOperation()->getPackage();
        $platformPackages = $this->uninstallPlatformPackages($package);

        $this->updatePackageComposerJson($package, $platformPackages, false);
    }

    /**
     * Process platform-specific installers for a package
     *
     * @return PlatformPackage[]
     */
    protected function processPlatformPackages(PackageInterface $package): array
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
        if (empty($validOperations)) return [];

        try {
            $installationManager->execute($localRepository, $validOperations);
            $this->io->write("Installed platform packages successfully");
        } catch (\Throwable $e) {
            $this->io->writeError("Failed to install platform packages: {$e->getMessage()}");
            throw $e;
        }

        return array_map(fn ($operation) => $operation->getPackage(), $validOperations);
    }

    protected function uninstallPlatformPackages(PackageInterface $package): array
    {
        $platformPackages = PlatformConfiguration::parse($package);

        $installationManager = $this->composer->getInstallationManager();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        $operations = array_map(
            fn ($platformPackage) => InstalledVersions::isInstalled($platformPackage->getName()) ? new UninstallOperation($platformPackage) : null,
            $platformPackages
        );

        $validOperations = array_filter($operations);
        if (empty($validOperations)) return [];

        try {
            $installationManager->execute($localRepository, $validOperations);

            $this->io->write("Uninstalled platform-specific packages successfully");
        } catch (\Throwable $e) {
            $this->io->writeError("Failed to uninstall platform packages: {$e->getMessage()}");
            throw $e;
        }

        return array_map(fn ($operation) => $operation->getPackage(), $validOperations);
    }

    protected function updatePackageComposerJson(PackageInterface $package, array $platformPackages, bool $add = true): void
    {
        $installationManager = $this->composer->getInstallationManager();
        $packagePath = $installationManager->getInstallPath($package);

        $composerJsonPath = rtrim($packagePath, '/').'/composer.json';

        if (!file_exists($composerJsonPath)) {
            file_put_contents($composerJsonPath, '{}');
        }

        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        if (!isset($composerConfig['require'])) {
            $composerConfig['require'] = [];
        }

        $modified = false;
        foreach ($platformPackages as $platformPackage) {
            $packageName = $platformPackage->getName();
            $packageVersion = $platformPackage->getPrettyVersion();

            // add if $add is true and not already in require
            if ($add && !isset($composerConfig['require'][$packageName])) {
                $composerConfig['require'][$packageName] = $packageVersion;
                $modified = true;
            }

            // remove if $add is false and already in require
            if (!$add && isset($composerConfig['require'][$packageName])) {
                unset($composerConfig['require'][$packageName]);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents(
                $composerJsonPath,
                json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->io->write("Updated composer.json");
        }
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
