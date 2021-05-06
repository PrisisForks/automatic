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

namespace Narrowspark\Automatic\AutoScripts;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\AbstractContainer;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\Common\ScriptExtender\PhpScriptExtender;
use Narrowspark\Automatic\Common\Traits\GetGenericPropertyReaderTrait;
use Narrowspark\Automatic\AutoScripts\ScriptExtender\ScriptExtender;
use Symfony\Component\Console\Input\InputInterface;
use function array_merge;
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
                return array_merge(
                    [
                        Automatic::COMPOSER_EXTRA_KEY => [
                            'allow-auto-install' => false,
                            'dont-discover' => [],
                        ],
                    ],
                    $container->get(Composer::class)->getPackage()->getExtra()
                );
            },
            InputInterface::class => static function (ContainerContract $container) use ($genericPropertyReader): InputInterface {
                return $genericPropertyReader($container->get(IOInterface::class), 'input');
            },
            ScriptExecutor::class => static function (ContainerContract $container): ScriptExecutor {
                $scriptExecutor = new ScriptExecutor(
                    $container->get(Composer::class),
                    $container->get(IOInterface::class),
                    new ProcessExecutor(),
                    $container->get('composer-extra')
                );

                $scriptExecutor->add(ScriptExtender::getType(), ScriptExtender::class);
                $scriptExecutor->add(PhpScriptExtender::getType(), PhpScriptExtender::class);

                return $scriptExecutor;
            }
        ]);
    }
}
