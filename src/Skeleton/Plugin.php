<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/automatic
 */

namespace Narrowspark\Automatic\Skeleton;

use Composer\Command\GlobalCommand;
use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Installer\InstallationManager;
use Narrowspark\Automatic\Common\Lock;
use Narrowspark\Automatic\Skeleton\Common\Util;
use Narrowspark\Automatic\Skeleton\Installer\SkeletonInstaller;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use const DIRECTORY_SEPARATOR;
use const PHP_INT_MAX;
use function class_exists;
use function debug_backtrace;
use function dirname;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function version_compare;

/**
 * @noRector \Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector
 */
final class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** @var string */
    public const VERSION = '0.0.0';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'skeleton';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic-composer-skeleton';

    /**
     * @var \Narrowspark\Automatic\Common\Contract\Container
     */
    private $container;

    /**
     * Check if the the plugin is activated.
     *
     * @var bool
     */
    private static $activated = true;

    /**
     * Check for global command.
     *
     * @var bool
     */
    private static $isGlobalCommand = false;

    /**
     * Get the automatic.lock or skeleton.lock file path.
     */
    public static function getAutomaticLockFile(): string
    {
        $key = self::COMPOSER_EXTRA_KEY;

        if (class_exists(Automatic::class)) {
            $key = Automatic::COMPOSER_EXTRA_KEY;
        }

        return str_replace('composer', $key, Util::getComposerLockFile());
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        // to avoid issues when Automatic Skeleton is upgraded, we load all PHP classes now
        // that way, we are sure to use all classes from the same version.
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 1), FilesystemIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if (substr($file->getFilename(), -4) === '.php') {
                class_exists(__NAMESPACE__ . str_replace('/', '\\', substr($file->getFilename(), strlen(__DIR__), -4)));
            }
        }

        if (! class_exists(AbstractContainer::class)) {
            require __DIR__ . DIRECTORY_SEPARATOR . 'alias.php';
        }

        if (($errorMessage = $this->getErrorMessage($io)) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Skeleton has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        $this->container = new Container($composer, $io);

        if ($this->container->get(InputInterface::class) === null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic Skeleton has been disabled. No input object found on composer class.</warning>');

            return;
        }

        /** @var \Composer\Installer\InstallationManager $installationManager */
        $installationManager = $composer->getInstallationManager();
        $installationManager->addInstaller($this->container->get(SkeletonInstaller::class));

        $this->extendComposer(debug_backtrace());
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed[][][]|string[]
     */
    public static function getSubscribedEvents(): array
    {
        if (! self::$activated) {
            return [];
        }

        return [
            ScriptEvents::POST_CREATE_PROJECT_CMD => [
                ['onPostCreateProject', PHP_INT_MAX],
                ['runSkeletonGenerator', PHP_INT_MAX - 1],
            ],
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        self::$activated = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->container->get(Lock::class)->delete();
    }

    /**
     * Executes on composer create project event.
     *
     * @throws Exception
     */
    public function onPostCreateProject(Event $event): void
    {
        if (self::$isGlobalCommand) {
            return;
        }

        [$json, $manipulator] = Util::getComposerJsonFileAndManipulator();

        // new projects are most of the time proprietary
        $manipulator->addMainKey('license', 'proprietary');

        // 'name' and 'description' are only required for public packages
        $manipulator->removeProperty('name');
        $manipulator->removeProperty('description');

        foreach ($this->container->get('composer-extra') as $key => $value) {
            if ($key !== self::COMPOSER_EXTRA_KEY) {
                $manipulator->addSubNode('extra', $key, $value);
            }
        }

        $this->container->get(Filesystem::class)->dumpFile($json->getPath(), $manipulator->getContents());

        Util::updateComposerLock($this->container->get(Composer::class), $this->container->get(IOInterface::class));
    }

    /**
     * Run found skeleton generators.
     *
     * @throws Exception
     */
    public function runSkeletonGenerator(Event $event): void
    {
        if (self::$isGlobalCommand) {
            return;
        }

        /** @var \Narrowspark\Automatic\Common\Lock $lock */
        $lock = $this->container->get(Lock::class);

        $lock->read();

        if ($lock->has(SkeletonInstaller::LOCK_KEY)) {
            $this->operations = [];

            $skeletonGenerator = new SkeletonGenerator(
                $this->container->get(IOInterface::class),
                $this->container->get(InstallationManager::class),
                $lock,
                $this->container->get('vendor-dir'),
                $this->container->get('composer-extra')
            );

            $skeletonGenerator->run();
            $skeletonGenerator->selfRemove();
        } else {
            $lock->reset();
        }
    }

    /**
     * Check if automatic can be activated.
     */
    private function getErrorMessage(IOInterface $io): ?string
    {
        // @codeCoverageIgnoreStart
        if (! extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your [php.ini] file';
        }

        if (version_compare(Util::getComposerVersion(), '2.0.0', '<')) {
            return sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }

        // @codeCoverageIgnoreEnd

        // skip on no interactive mode
        if (! $io->isInteractive()) {
            return 'Composer running in a no interaction mode';
        }

        return null;
    }

    /**
     * Extend the composer object with some automatic settings.
     *
     * @param array<int|string, mixed> $backtrace
     */
    private function extendComposer(array $backtrace): void
    {
        foreach ($backtrace as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof Installer) {
                /** @var \Composer\Installer $installer */
                $installer = $trace['object'];
                $installer->setSuggestedPackagesReporter(new SuggestedPackagesReporter(new NullIO()));

                break;
            }
        }

        foreach ($backtrace as $trace) {
            if (! isset($trace['object']) || ! isset($trace['args'][0])) {
                continue;
            }

            if ($trace['object'] instanceof GlobalCommand) {
                self::$isGlobalCommand = true;
            }

            if (! $trace['object'] instanceof Application || ! $trace['args'][0] instanceof ArgvInput) {
                continue;
            }

            /** @var InputInterface $input */
            $input = $trace['args'][0];
            $app = $trace['object'];

            try {
                /** @var null|string $command */
                $command = $input->getFirstArgument();
                $command = $command !== null ? $app->find($command)->getName() : null;
            } catch (InvalidArgumentException $e) {
                $command = null;
            }

            if ($command === 'create-project') {
                $input->setOption('remove-vcs', true);
            }

            break;
        }
    }
}
