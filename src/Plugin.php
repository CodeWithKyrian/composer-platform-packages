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
