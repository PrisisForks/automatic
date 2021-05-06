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

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\ClassFinder;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Lock;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\Skeleton\Installer\SkeletonInstaller;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use function rtrim;

/**
 * @internal
 */
final class Container extends AbstractContainer
{
    use GetGenericPropertyReaderTrait;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $genericPropertyReader = $this->getGenericPropertyReader();

        parent::__construct([
            Composer::class => static function () use ($composer): Composer {
                return $composer;
            },
            Config::class => static function (ContainerContract $container): Config {
                return $container->get(Composer::class)->getConfig();
            },
            IOInterface::class => static function () use ($io): IOInterface {
                return $io;
            },
            'vendor-dir' => static function (ContainerContract $container): string {
                return rtrim($container->get(Config::class)->get('vendor-dir'), '/');
            },
            'composer-extra' => static function (ContainerContract $container): array {
                return $container->get(Composer::class)->getPackage()->getExtra();
            },
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader): InputInterface {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            Lock::class => static function (): Lock {
                return new Lock(Plugin::getAutomaticLockFile());
            },
            ClassFinder::class => static function (ContainerContract $container): ClassFinder {
                return new ClassFinder($container->get('vendor-dir'));
            },
            SkeletonInstaller::class => static function (ContainerContract $container): SkeletonInstaller {
                return new SkeletonInstaller(
                    $container->get(IOInterface::class),
                    $container->get(Composer::class),
                    $container->get(Lock::class),
                    $container->get(ClassFinder::class)
                );
            },
            Filesystem::class => static function (): Filesystem {
                return new Filesystem();
            },
        ]);
    }
}
