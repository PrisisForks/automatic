<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Narrowspark\Discovery\ClassFinder;
use Narrowspark\Discovery\Common\Contract\Configurator as ConfiguratorContract;
use Narrowspark\Discovery\Lock;
use UnexpectedValueException;

class ConfiguratorInstaller extends LibraryInstaller
{
    /**
     * @var string
     */
    public const TYPE = 'discovery-configurator';

    /**
     * @var string
     */
    public const LOCK_KEY = '_configurators';

    /**
     * A lock instance.
     *
     * @var \Narrowspark\Discovery\Lock
     */
    private $lock;

    /**
     * {@inheritdoc}
     */
    public function __construct(IOInterface $io, Composer $composer, Lock $lock)
    {
        parent::__construct($io, $composer, self::TYPE);

        $this->lock = $lock;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($packageType): bool
    {
        return $packageType === self::TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        $autoload = $package->getAutoload();

        if (empty($autoload['psr4'])) {
            throw new UnexpectedValueException('Error while installing ' . $package->getPrettyName() . ', discovery-configurator packages should have a namespace defined in their psr4 key to be usable.');
        }

        parent::install($repo, $package);

        $configurators = $this->saveConfiguratorsToLockFile($autoload);

        if (empty($configurators)) {
            // Rollback installation
            $this->io->writeError('Configurator installation failed, rolling back');

            parent::uninstall($repo, $package);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): void
    {
        parent::update($repo, $initial, $target);

        $autoload = $target->getAutoload();

        $this->saveConfiguratorsToLockFile($autoload);
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): void
    {
        parent::uninstall($repo, $package);

        $this->lock->remove(self::LOCK_KEY);
    }

    /**
     * Finds all class in given namespace and save it to discovery lock file.
     *
     * @param array $autoload
     *
     * @return array
     */
    protected function saveConfiguratorsToLockFile(array $autoload): array
    {
        $psr4Namespaces = $autoload['psr4'];

        $configurators = [];

        foreach ((array) $psr4Namespaces as $namespace => $path) {
            foreach (ClassFinder::find($path, $namespace) as $class) {
                if (\in_array(ConfiguratorContract::class, \class_implements($class), true)) {
                    $configurators[$class] = $class;
                }
            }
        }

        if ($this->lock->has(self::LOCK_KEY)) {
            $configurators = \array_merge($this->lock->get(self::LOCK_KEY), $configurators);
        }

        $this->lock->add(self::LOCK_KEY, $configurators);

        return $configurators;
    }
}