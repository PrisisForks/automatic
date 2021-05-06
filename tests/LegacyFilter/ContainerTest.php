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

namespace Narrowspark\Automatic\Tests\LegacyFilter;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Mockery;
use Narrowspark\Automatic\LegacyFilter\Container;
use Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use Narrowspark\Automatic\LegacyFilter\LegacyTagsManager;
use Narrowspark\Automatic\Tests\Helper\AbstractMockeryTestCase;
use Symfony\Component\Console\Input\InputInterface;
use function is_array;
use function is_object;
use function is_string;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\LegacyFilter\Container
 *
 * @medium
 */
final class ContainerTest extends AbstractMockeryTestCase
{
    /** @var \Narrowspark\Automatic\LegacyFilter\Container */
    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $composer = new Composer();

        /** @var \Composer\Config|\Mockery\MockInterface $configMock */
        $configMock = Mockery::mock(Config::class);
        $configMock->shouldReceive('get')
            ->with('vendor-dir')
            ->andReturn('/vendor');
        $configMock->shouldReceive('get')
            ->with('cache-files-dir')
            ->andReturn('');
        $configMock->shouldReceive('get')
            ->with('disable-tls')
            ->andReturn(true);
        $configMock->shouldReceive('get')
            ->with('bin-dir')
            ->andReturn(__DIR__);
        $configMock->shouldReceive('get')
            ->with('bin-compat')
            ->andReturn(__DIR__);
        $configMock->shouldReceive('get')
            ->with('cache-repo-dir')
            ->andReturn(__DIR__);

        $composer->setConfig($configMock);

        /** @var \Composer\Package\RootPackageInterface|\Mockery\MockInterface $package */
        $package = Mockery::mock(RootPackageInterface::class);
        $package->shouldReceive('getExtra')
            ->andReturn([]);

        $composer->setPackage($package);

        /** @var \Composer\Downloader\DownloadManager|\Mockery\MockInterface $downloadManager */
        $downloadManager = Mockery::mock(DownloadManager::class);
        $downloadManager->shouldReceive('getDownloader')
            ->with('file');

        $composer->setDownloadManager($downloadManager);

        $this->container = new Container($composer, new BufferIO());
    }

    /**
     * @dataProvider provideContainerInstancesCases
     *
     * @param class-string<object>|mixed[] $expected
     */
    public function testContainerInstances(string $key, $expected): void
    {
        $value = $this->container->get($key);

        if (is_string($value) || (is_array($value) && is_array($expected))) {
            self::assertSame($expected, $value);
        }

        if (is_object($value) && is_string($expected)) {
            self::assertInstanceOf($expected, $value);
        }
    }

    /**
     * @return array<int, array<int|string, mixed>|string>
     */
    public static function provideContainerInstancesCases(): iterable
    {
        return [
            [Composer::class, Composer::class],
            [Config::class, Config::class],
            [IOInterface::class, BufferIO::class],
            [InputInterface::class, InputInterface::class],
            [LegacyTagsManagerContract::class, LegacyTagsManager::class],
            ['composer-extra', []],
        ];
    }

    public function testGetAll(): void
    {
        self::assertCount(7, $this->container->getAll());
    }
}
