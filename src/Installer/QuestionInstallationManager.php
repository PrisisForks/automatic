<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\Installer as BaseInstaller;
use Composer\Installer\InstallationManager as BaseInstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryFactory;
use Narrowspark\Discovery\Common\Exception\InvalidArgumentException;
use Narrowspark\Discovery\Common\Exception\RuntimeException;
use Narrowspark\Discovery\Discovery;
use Narrowspark\Discovery\OperationsResolver;
use Narrowspark\Discovery\Traits\GetGenericPropertyReaderTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
class QuestionInstallationManager
{
    use GetGenericPropertyReaderTrait;

    /**
     * @var int
     */
    private const ADD = 1;

    /**
     * @var int
     */
    private const REMOVE = 0;

    /**
     * All local installed packages.
     *
     * @var string[]
     */
    private $installedPackages;

    /**
     * Get the minimum stability.
     *
     * @var string
     */
    private $stability;

    /**
     * The composer vendor path.
     *
     * @var string
     */
    private $vendorPath;

    /**
     * A VersionSelector instance.
     *
     * @var \Composer\Package\Version\VersionSelector
     */
    private $versionSelector;

    /**
     * A root package implementation.
     *
     * @var \Composer\Package\RootPackageInterface
     */
    private $rootPackage;

    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A repository implementation.
     *
     * @var \Composer\Repository\WritableRepositoryInterface
     */
    private $localRepository;

    /**
     * A input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * Create a new ExtraDependencyInstaller instance.
     *
     * @param \Composer\Composer                              $composer
     * @param \Composer\IO\IOInterface                        $io
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param string                                          $vendorPath
     */
    public function __construct(Composer $composer, IOInterface $io, InputInterface $input, string $vendorPath)
    {
        $this->composer   = $composer;
        $this->io         = $io;
        $this->input      = $input;
        $this->vendorPath = $vendorPath;

        $this->rootPackage = $composer->getPackage();
        $this->stability   = $this->rootPackage->getMinimumStability() ?: 'stable';

        $pool = new Pool($this->stability);
        $pool->addRepository(
            new CompositeRepository(\array_merge([new PlatformRepository()], RepositoryFactory::defaultRepos($io)))
        );

        $this->versionSelector = new VersionSelector($pool);
        $this->localRepository = $composer->getRepositoryManager()->getLocalRepository();

        foreach ($this->localRepository->getPackages() as $package) {
            $this->installedPackages[$package->getName()] = $package->getPrettyVersion();
        }
    }

    /**
     * Install selected extra dependencies.
     *
     * @param string $name
     * @param array  $dependencies
     *
     * @throws \Narrowspark\Discovery\Common\Exception\RuntimeException
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     * @throws \Exception
     *
     * @return \Narrowspark\Discovery\Package[]
     */
    public function install(string $name, array $dependencies): array
    {
        if (! $this->io->isInteractive() || \count($dependencies) === 0) {
            // Do nothing in no-interactive mode
            return [];
        }

        $packagesToInstall = [];
        $rootPackages      = [];

        foreach ($this->getRootRequires() as $link) {
            $rootPackages[$link->getTarget()] = $link->getTarget();
        }

        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addDiscoveryInstallationManagerToComposer($oldInstallManager);

        foreach ($dependencies as $question => $options) {
            if (! \is_array($options) || \count($options) < 2) {
                throw new RuntimeException('You must provide at least two optional dependencies.');
            }

            foreach ($options as $package => $version) {
                // Check if package variable is a integer
                if (\is_int($package)) {
                    $package = $version;
                }

                // Package has been already prepared to be installed, skipping.
                // Package from this group has been found in root composer, skipping.
                if (isset($packagesToInstall[$package]) || isset($rootPackages[$package])) {
                    continue 2;
                }

                // Check if package is currently installed, if so, use installed constraint and skip question.
                if (isset($this->installedPackages[$package])) {
                    $packagesToInstall[$package] = $this->installedPackages[$package];

                    continue 2;
                }
            }

            $package    = $this->askDependencyQuestion($question, $options);
            $constraint = $options[$package] ?? $this->findVersion($package);

            $this->io->writeError(\sprintf('Using version <info>%s</info> for <info>%s</info>', $constraint, $package));

            $packagesToInstall[$package] = $constraint;
        }

        if (\count($packagesToInstall) !== 0) {
            $this->updateComposerJson($packagesToInstall, self::ADD);

            $this->runInstaller(
                $this->updateRootComposerJson($packagesToInstall, self::ADD),
                \array_keys($packagesToInstall)
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Revert to the old install manager.
        $this->composer->setInstallationManager($oldInstallManager);

        $resolver = new OperationsResolver($operations, $this->vendorPath);
        $resolver->setParentPackageName($name);

        return $resolver->resolve();
    }

    /**
     * Uninstall extra dependencies.
     *
     * @param string $name
     * @param array  $dependencies
     *
     * @throws \Exception
     *
     * @return \Narrowspark\Discovery\Package[]
     */
    public function uninstall(string $name, array $dependencies): array
    {
        $oldInstallManager = $this->composer->getInstallationManager();

        $this->addDiscoveryInstallationManagerToComposer($oldInstallManager);

        if (\count($dependencies) !== 0) {
            $this->updateComposerJson($dependencies, self::REMOVE);
            $packages = $this->localRepository->getPackages();

            $whiteList = $dependencies;

            foreach ($packages as $package) {
                if ($package->getName() === $name) {
                    $whiteList += \array_keys($package->getRequires());
                }
            }

            foreach ($packages as $package) {
                $mixedRequires = \array_keys($package->getRequires()) + \array_keys($package->getDevRequires());

                foreach ($whiteList as $whitelistPackageName) {
                    if (isset($mixedRequires[$whitelistPackageName])) {
                        unset($whiteList[$whitelistPackageName]);
                    }
                }
            }

            $this->runInstaller(
                $this->updateRootComposerJson($dependencies, self::REMOVE),
                $whiteList
            );
        }

        $operations = $this->composer->getInstallationManager()->getOperations();

        // Revert to the old install manager.
        $this->composer->setInstallationManager($oldInstallManager);

        $resolver = new OperationsResolver($operations, $this->vendorPath);
        $resolver->setParentPackageName($name);

        return $resolver->resolve();
    }

    /**
     * @codeCoverageIgnore
     *
     * Get configured installer instance.
     *
     * @return \Composer\Installer
     */
    protected function getInstaller(): BaseInstaller
    {
        return Installer::create($this->io, $this->composer, $this->input);
    }

    /**
     * Build question and ask it.
     *
     * @param string $question
     * @param array  $packages
     *
     * @throws \Exception
     *
     * @return string
     */
    private function askDependencyQuestion(string $question, array $packages): string
    {
        $ask          = \sprintf('<question>%s</question>' . "\n", $question);
        $i            = 0;
        $packageNames = [];

        foreach ($packages as $packageName => $version) {
            if (\is_int($packageName)) {
                $packageName = $version;
            }

            $packageNames[] = $packageName;

            if ($packageName === $version) {
                $version = $this->findVersion($packageName);
            }

            $ask .= \sprintf('  [<comment>%d</comment>] %s%s' . "\n", $i, $packageName,  ' : ' . $version);

            $i++;
        }

        $ask .= '  Make your selection: ';

        do {
            $package = $this->io->askAndValidate(
                $ask,
                function ($input) use ($packageNames) {
                    $input = \is_numeric($input) ? (int) \trim($input) : -1;

                    return $packageNames[$input] ?? null;
                }
            );
        } while (! $package);

        return $package;
    }

    /**
     * Try to find the best version fot the package.
     *
     * @param string $name
     *
     * @throws \Narrowspark\Discovery\Common\Exception\InvalidArgumentException
     *
     * @return string
     */
    private function findVersion(string $name): string
    {
        // find the latest version allowed in this pool
        $package = $this->versionSelector->findBestCandidate($name, null, null, 'stable');

        if ($package === false) {
            throw new InvalidArgumentException(sprintf(
                'Could not find package %s at any version for your minimum-stability (%s).'
                . ' Check the package spelling or your minimum-stability.',
                $name,
                $this->stability
            ));
        }

        return $this->versionSelector->findRecommendedRequireVersion($package);
    }

    /**
     * Update the root composer.json require.
     *
     * @param array $packages
     * @param int   $type
     *
     * @return \Composer\Package\RootPackageInterface
     */
    private function updateRootComposerJson(array $packages, int $type): RootPackageInterface
    {
        $this->io->writeError('Updating root package');

        $requires = $this->rootPackage->getRequires();

        if ($type === self::ADD) {
            foreach ($packages as $name => $version) {
                $requires[$name] = new Link(
                    '__root__',
                    $name,
                    (new VersionParser())->parseConstraints($version),
                    'requires',
                    $version
                );
            }
        } elseif ($type === self::REMOVE) {
            foreach ($packages as $package) {
                unset($requires[$package]);
            }
        }

        $this->rootPackage->setRequires($requires);

        return $this->rootPackage;
    }

    /**
     * Manipulate root composer.json with the new packages and dump it.
     *
     * @param array $packages
     * @param int   $type
     *
     * @return void
     */
    private function updateComposerJson(array $packages, int $type): void
    {
        $this->io->writeError('Updating composer.json');

        [$json, $manipulator] = Discovery::getComposerJsonFileAndManipulator();

        if ($type === self::ADD) {
            foreach ($packages as $name => $version) {
                $sortPackages = $this->composer->getConfig()->get('sort-packages') ?? false;

                $manipulator->addLink('require', $name, $version, $sortPackages);
            }
        } elseif ($type === self::REMOVE) {
            foreach ($packages as $package) {
                $manipulator->removeSubNode('require', $package);
            }
        }

        \file_put_contents($json->getPath(), $manipulator->getContents());
    }

    /**
     * Install selected packages.
     *
     * @param \Composer\Package\RootPackageInterface $rootPackage
     * @param array                                  $whitelistPackages
     *
     * @throws \Exception
     *
     * @return int
     */
    private function runInstaller(RootPackageInterface $rootPackage, array $whitelistPackages): int
    {
        $this->io->writeError('Running an update to install dependent packages');

        $this->composer->setPackage($rootPackage);

        $installer = $this->getInstaller();
        $installer->setUpdateWhitelist($whitelistPackages);

        return $installer->run();
    }

    /**
     * Adds a modified installation manager to composer.
     *
     * @param \Composer\Installer\InstallationManager $oldInstallManager
     *
     * @return void
     */
    private function addDiscoveryInstallationManagerToComposer(BaseInstallationManager $oldInstallManager): void
    {
        $reader     = $this->getGenericPropertyReader();
        $installers = (array) $reader($oldInstallManager, 'installers');

        $narrowsparkInstaller = new InstallationManager();

        foreach ($installers as $installer) {
            $narrowsparkInstaller->addInstaller($installer);
        }

        $this->composer->setInstallationManager($narrowsparkInstaller);
    }

    /**
     * Get merged root requires and dev-requires.
     *
     * @return \Composer\Package\Link[]
     */
    private function getRootRequires(): array
    {
        return \array_merge($this->rootPackage->getRequires(), $this->rootPackage->getDevRequires());
    }
}
