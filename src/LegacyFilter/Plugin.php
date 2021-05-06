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

namespace Narrowspark\Automatic\LegacyFilter;

use Composer\Composer;
use Composer\Console\Application;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PrePoolCreateEvent;
use FilesystemIterator;
use InvalidArgumentException;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\LegacyFilter\Common\Util;
use Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use const DIRECTORY_SEPARATOR;
use function class_exists;
use function count;
use function debug_backtrace;
use function dirname;
use function explode;
use function getenv;
use function is_int;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function version_compare;

/**
 * @noRector \Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector
 */
final class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** @var string */
    public const VERSION = '0.13.1';

    /** @var string */
    public const COMPOSER_EXTRA_KEY = 'legacy-filter';

    /** @var string */
    public const PACKAGE_NAME = 'narrowspark/automatic-composer-legacy-filter';

    /**
     * A Container instance.
     *
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
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        // to avoid issues when Automatic LegacyFilter is upgraded, we load all PHP classes now
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

        if (($errorMessage = $this->getErrorMessage()) !== null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic LegacyFilter has been disabled. ' . $errorMessage . '</warning>');

            return;
        }

        $this->container = new Container($composer, $io);

        if ($this->container->get(InputInterface::class) === null) {
            self::$activated = false;

            $io->writeError('<warning>Narrowspark Automatic LegacyFilter has been disabled. No input object found on composer class.</warning>');

            return;
        }

        /** @var \Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager $tagsManager */
        $tagsManager = $this->container->get(LegacyTagsManagerContract::class);

        $this->configureLegacyTagsManager($io, $tagsManager, $this->container->get('composer-extra'));

        // overwrite composer instance
        $this->container->set(Composer::class, static function () use ($composer): Composer {
            return $composer;
        });

        $this->extendComposer(debug_backtrace(), $tagsManager);
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
            PluginEvents::PRE_POOL_CREATE => 'truncatePackages',
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        self::$activated = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public function truncatePackages(PrePoolCreateEvent $event): void
    {
        /** @var \Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager $tagsManager */
        $tagsManager = $this->container->get(LegacyTagsManagerContract::class);

        $rootPackage = $this->container->get(Composer::class)->getPackage();
        $lockedPackages = $event->getRequest()->getFixedOrLockedPackages();

        $this->container->get(IOInterface::class)->writeError(count($event->getPackages()) . ' ' . count($tagsManager->removeLegacyTags($event->getPackages(), $rootPackage, $lockedPackages)));
        $event->setPackages($tagsManager->removeLegacyTags($event->getPackages(), $rootPackage, $lockedPackages));
    }

    /**
     * Configure the LegacyTagsManager with legacy package requires.
     */
    private function configureLegacyTagsManager(
        IOInterface $io,
        LegacyTagsManagerContract $tagsManager,
        array $extra
    ): void {
        if (false !== $envRequire = getenv('AUTOMATIC_LEGACY_FILTER_REQUIRE')) {
            $requires = [];

            foreach (explode(',', $envRequire) as $packageString) {
                [$packageName, $version] = explode(':', $packageString, 2);

                $requires[$packageName] = $version;
            }

            $this->addLegacyTags($io, $requires, $tagsManager);
        } elseif (isset($extra[self::COMPOSER_EXTRA_KEY]['require'])) {
            $this->addLegacyTags($io, $extra[self::COMPOSER_EXTRA_KEY]['require'], $tagsManager);
        }

        $this->container->set(LegacyTagsManagerContract::class, static function () use ($tagsManager): LegacyTagsManagerContract {
            return $tagsManager;
        });
    }

    /**
     * Add found legacy tags to the tags manager.
     */
    private function addLegacyTags(IOInterface $io, array $requires, LegacyTagsManagerContract $tagsManager): void
    {
        foreach ($requires as $name => $version) {
            if (is_int($name)) {
                $io->writeError(sprintf('Constrain [%s] skipped, because package name is a number [%s]', $version, $name));

                continue;
            }

            if (strpos($name, '/') === false) {
                $io->writeError(sprintf('Constrain [%s] skipped, package name [%s] without a slash is not supported', $version, $name));

                continue;
            }

            $tagsManager->addConstraint($name, $version);
        }
    }

    /**
     * Check if automatic can be activated.
     */
    private function getErrorMessage(): ?string
    {
        // @codeCoverageIgnoreStart
        if (! extension_loaded('openssl')) {
            return 'You must enable the openssl extension in your [php.ini] file';
        }

        if (version_compare(Util::getComposerVersion(), '2.0.0', '<')) {
            return sprintf('Your version "%s" of Composer is too old; Please upgrade', Composer::VERSION);
        }
        // @codeCoverageIgnoreEnd

        return null;
    }

    /**
     * Extend the composer object with some automatic legacy filter settings.
     *
     * @param array<int|string, mixed> $backtrace
     */
    private function extendComposer(array $backtrace, LegacyTagsManagerContract $tagsManager): void
    {
        foreach ($backtrace as $trace) {
            if (! isset($trace['object']) || ! isset($trace['args'][0])) {
                continue;
            }

            if (! $trace['object'] instanceof Application || ! $trace['args'][0] instanceof ArgvInput) {
                continue;
            }

            /** @var \Symfony\Component\Console\Input\InputInterface $input */
            $input = $trace['args'][0];
            $app = $trace['object'];

            try {
                /** @var null|string $command */
                $command = $input->getFirstArgument();
                $command = $command !== null ? $app->find($command)->getName() : null;
            } catch (InvalidArgumentException $e) {
                $command = null;
            }

            if ($command === 'outdated') {
                $tagsManager->reset();
            }

            // When prefer-lowest is set and no stable version has been released,
            // we consider "dev" more stable than "alpha", "beta" or "RC". This
            // allows testing lowest versions with potential fixes applied.
            if ($input->hasParameterOption('--prefer-lowest', true)) {
                BasePackage::$stabilities['dev'] = 1 + BasePackage::STABILITY_STABLE;
            }

            break;
        }
    }
}
