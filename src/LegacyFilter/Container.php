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
use Composer\Config;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Symfony\Component\Console\Input\InputInterface;
use function getenv;
use function rtrim;

/**
 * @internal
 */
final class Container extends AbstractContainer
{
    use GetGenericPropertyReaderTrait;

    /**
     * Instantiate the container.
     */
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
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader): ?InputInterface {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            HttpDownloader::class => static function (ContainerContract $container): HttpDownloader {
                return Factory::createHttpDownloader(
                    $container->get(IOInterface::class),
                    $container->get(Config::class)
                );
            },
            LegacyTagsManagerContract::class => static function (ContainerContract $container): LegacyTagsManagerContract {
                $composer = $container->get(Composer::class);
                $io = $container->get(IOInterface::class);

                $endpoint = getenv('AUTOMATIC_LEGACY_FILTER_ENDPOINT');

                if ($endpoint === false) {
                    $endpoint = $composer->getPackage()->getExtra()[Plugin::COMPOSER_EXTRA_KEY]['endpoint'] ?? 'https://automatic.narrowspark.com/versions/';
                }

                $downloader = new Downloader(
                    rtrim($endpoint, '/'),
                    $composer,
                    $io,
                    $container->get(HttpDownloader::class)
                );

                return new LegacyTagsManager($io, $downloader);
            },
            'composer-extra' => static function (ContainerContract $container): array {
                return $container->get(Composer::class)->getPackage()->getExtra();
            },
        ]);
    }
}
