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

use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Link;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Semver\Constraint\Constraint;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\Common\Downloader\JsonResponse;
use Narrowspark\Automatic\LegacyFilter\LegacyTagsManager;
use Narrowspark\Automatic\Tests\Helper\AbstractMockeryTestCase;
use const DIRECTORY_SEPARATOR;
use function array_map;
use function glob;
use function is_dir;
use function rmdir;
use function sprintf;
use function unlink;
use function usort;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\LegacyFilter\LegacyTagsManager
 *
 * @medium
 */
final class LegacyTagsManagerTest extends AbstractMockeryTestCase
{
    /** @var array<string, string> */
    private $downloadFileList;

    /** @var \Composer\IO\IOInterface|MockInterface */
    private $ioMock;

    /** @var \Narrowspark\Automatic\LegacyFilter\LegacyTagsManager */
    private $tagsManger;

    /** @var JsonResponse|LegacyMockInterface|MockInterface */
    private MockInterface |

LegacyMockInterface $responseMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pPath = __DIR__ . DIRECTORY_SEPARATOR . 'Fixture' . DIRECTORY_SEPARATOR . 'Packagist' . DIRECTORY_SEPARATOR;

        $this->downloadFileList = [
            'cakephp$cakephp' => $pPath . 'provider-cakephp$cakephp.json',
            'codeigniter$framework' => $pPath . 'provider-codeigniter$framework.json',
            'symfony$security-guard' => $pPath . 'provider-symfony$security-guard.json',
            'symfony$symfony' => $pPath . 'provider-symfony$symfony.json',
            'zendframework$zend-diactoros' => $pPath . 'provider-zendframework$zend-diactoros.json',
        ];

        $this->responseMock = Mockery::mock(JsonResponse::class);

        $downloaderMock = Mockery::mock(Downloader::class);
        $downloaderMock->shouldReceive('get')
            ->andReturn($this->responseMock);

        $this->ioMock = Mockery::mock(IOInterface::class);
        $this->tagsManger = new LegacyTagsManager(
            $this->ioMock,
            $downloaderMock
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $path = __DIR__ . '/tests/LegacyFilter/https---flex.symfony.com';

        $this->delete($path);
        @rmdir($path);
    }

    public function testHasProvider(): void
    {
        $count = 0;

        $this->tagsManger->addConstraint('symfony/security-guard', '>=4.1');

        foreach ($this->downloadFileList as $file) {
            if ($this->tagsManger->hasProvider($file)) {
                $count++;
            }
        }

        self::assertSame(2, $count);
    }

    public function testReset(): void
    {
        $count = 0;

        $this->tagsManger->addConstraint('symfony/security-guard', '>=4.1');
        $this->tagsManger->reset();

        foreach ($this->downloadFileList as $file) {
            if ($this->tagsManger->hasProvider($file)) {
                $count++;
            }
        }

        self::assertSame(0, $count);
    }

    /**
     * @dataProvider provideRemoveLegacyTagsCases
     *
     * @param array<string, array<int|string, array<int|string, array>>> $expected
     * @param array<string, array<int|string, array<int|string, array>>> $packages
     * @param array<string, string>                                      $constraint
     * @param array<string, array<string, array<array-key, string>>>     $versions
     * @param array<string, string>                                      $messages
     * @param array<string, array<int|string, array<int|string, array>>> $lockedPackages
     */
    public function testRemoveLegacyTags(
        array $expected,
        array $packages,
        array $constraint,
        array $versions,
        array $messages,
        array $lockedPackages = []
    ): void {
        $hasSymfony = 0;

        foreach ($constraint as $name => $version) {
            if ($name === 'symfony/symfony') {
                $hasSymfony = 1;
            }

            $this->tagsManger->addConstraint($name, $version);
        }

        $this->responseMock->shouldReceive('getBody')
            ->times($hasSymfony)
            ->andReturn($versions);

        foreach ($messages as $name => $version) {
            $this->tagsManger->addConstraint($name, $version);

            $this->ioMock->shouldReceive('writeError')
                ->with(sprintf('<info>Restricting packages listed in [%s] to [%s]</info>', $name, $version));
        }

        $configToPackage = static function (array $configs): array {
            $l = new ArrayLoader();
            $packages = [];

            foreach ($configs as $name => $versions) {
                foreach ($versions as $version => $extra) {
                    $packages[] = $l->load([
                        'name' => $name,
                        'version' => $version,
                    ] + $extra, CompletePackage::class);
                }
            }

            return $packages;
        };

        /**
         * @param PackageInterface $a
         * @param PackageInterface $b
         *
         * @retrun int<-1, 1>
         */
        $sortPackages = static function (PackageInterface $a, PackageInterface $b): int {
            return [$a->getName(), $a->getVersion()] <=> [$b->getName(), $b->getVersion()];
        };

        $expected = $configToPackage($expected);
        $packages = $configToPackage($packages);
        $lockedPackages = $configToPackage($lockedPackages);

        $rootPackage = new RootPackage('test/test', '1.0.0.0', '1.0');
        $rootPackage->setRequires([
            'symfony/bar' => new Link('__root__', 'symfony/bar', new Constraint('>=', '3.0.0.0')),
        ]);

        $actual = $this->tagsManger->removeLegacyTags($packages, $rootPackage, $lockedPackages);

        usort($expected, $sortPackages);
        usort($actual, $sortPackages);

        self::assertEquals($expected, $actual);
    }

    /**
     * @return iterable{0: array<string, array<int|string, array<int|string, array>>>, 1: array<string, array<int|string, array<int|string, array>>>, 2: array<string, string>, 3: array<string, array<string, array<array-key, string>>>, 4: array<string, string>, 5: array<string, array<int|string, array<int|string, array>>>}
     */
    public static function provideRemoveLegacyTagsCases(): iterable
    {
        $branchAlias = static function (string $versionAlias): array {
            return [
                'extra' => [
                    'branch-alias' => [
                        'dev-master' => $versionAlias . '-dev',
                    ],
                ],
            ];
        };

        $packages = [
            'foo/unrelated' => [
                '1.0.0' => [],
            ],
            'symfony/symfony' => [
                '3.3.0' => ['version_normalized' => '3.3.0.0'],
                '3.4.0' => ['version_normalized' => '3.4.0.0'],
                'dev-main' => $branchAlias('3.5'),
            ],
            'symfony/http-foundation' => [
                '3.3.0' => ['version_normalized' => '3.3.0.0'],
                '3.4.0' => ['version_normalized' => '3.4.0.0'],
                'dev-main' => $branchAlias('3.5'),
            ],
        ];

        $expected = $packages;

        yield 'empty-intersection-ignores-2' => [$packages, $expected, ['symfony/symfony' => '~2.0'], ['splits' => [
            'symfony/http-foundation' => ['3.3', '3.4', '3.5'],
        ]], ['symfony/symfony' => '~2.0']];
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    private function delete(string $path): void
    {
        array_map(function (string $value): void {
            if (is_dir($value)) {
                $this->delete($value);

                @rmdir($value);
            } else {
                @unlink($value);
            }
        }, (array) glob($path . DIRECTORY_SEPARATOR . '*'));
    }
}
