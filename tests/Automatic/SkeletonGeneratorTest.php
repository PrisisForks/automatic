<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\IO\IOInterface;
use Narrowspark\Automatic\Automatic;
use Narrowspark\Automatic\Common\Package;
use Narrowspark\Automatic\Installer\InstallationManager;
use Narrowspark\Automatic\Installer\SkeletonInstaller;
use Narrowspark\Automatic\Lock;
use Narrowspark\Automatic\SkeletonGenerator;
use Narrowspark\Automatic\Test\Fixture\ConsoleFixtureGenerator;
use Narrowspark\Automatic\Test\Fixture\FrameworkDefaultFixtureGenerator;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Assert;

/**
 * @internal
 */
final class SkeletonGeneratorTest extends MockeryTestCase
{
    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Automatic\Installer\InstallationManager
     */
    private $installationManagerMock;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Automatic\Lock
     */
    private $lockMock;

    /**
     * @var \Narrowspark\Automatic\SkeletonGenerator
     */
    private $skeletonGenerator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock                  = $this->mock(IOInterface::class);
        $this->installationManagerMock = $this->mock(InstallationManager::class);
        $this->lockMock                = $this->mock(Lock::class);

        $this->skeletonGenerator       = new SkeletonGenerator(
            $this->ioMock,
            $this->installationManagerMock,
            $this->lockMock,
            __DIR__,
            []
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        $path = __DIR__ . '/Fixture/test.php';

        if (\file_exists($path)) {
            @\unlink($path);
        }
    }

    public function testRun(): void
    {
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->withArgs(function ($requires, $devRequires) {
                Assert::assertInstanceOf(Package::class, $requires[0]);
                Assert::assertInternalType('array', $devRequires);

                return true;
            });
        $this->installationManagerMock->shouldReceive('run')
            ->once();

        $this->arrangeLock(
            [
                'test/generator' => [
                    '%vendor_path%/Fixture/ConsoleFixtureGenerator.php',
                ],
            ],
            [
                'test/generator' => [ConsoleFixtureGenerator::class],
            ]
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console'], 'console')
            ->andReturn(0);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [console] skeleton.\n");

        $this->skeletonGenerator->run();
    }

    public function testRunWithDefault(): void
    {
        $this->installationManagerMock->shouldReceive('install')
            ->once()
            ->with([], []);
        $this->installationManagerMock->shouldReceive('run')
            ->once();

        $this->arrangeLock(
            [
                'test/generator' => [
                    '%vendor_path%/Fixture/ConsoleFixtureGenerator.php',
                    '%vendor_path%/Fixture/FrameworkDefaultFixtureGenerator.php',
                ],
            ],
            [
                'test/generator' => [ConsoleFixtureGenerator::class, FrameworkDefaultFixtureGenerator::class],
            ]
        );

        $this->ioMock->shouldReceive('select')
            ->once()
            ->with('Please select a skeleton:', ['console', 'framework'], 'framework')
            ->andReturn(1);
        $this->ioMock->shouldReceive('write')
            ->with("\nGenerating [framework] skeleton.\n");

        $this->skeletonGenerator->run();
    }

    public function testRemove(): void
    {
        $this->arrangeLock(
            [
                'test/generator' => [
                    '%vendor_path%/Fixture/ConsoleFixtureGenerator.php',
                ],
            ],
            [
                'test/generator' => [ConsoleFixtureGenerator::class],
            ]
        );

        $this->installationManagerMock->shouldReceive('uninstall')
            ->once()
            ->with(\Mockery::type('array'), []);

        $this->lockMock->shouldReceive('remove')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY);
        $this->lockMock->shouldReceive('add')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP, []);
        $this->lockMock->shouldReceive('write')
            ->once();

        $this->skeletonGenerator->remove();
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }

    /**
     * @param array $classmap
     * @param array $generators
     */
    protected function arrangeLock(array $classmap, array $generators): void
    {
        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(Automatic::LOCK_CLASSMAP)
            ->andReturn($classmap);

        $this->lockMock->shouldReceive('get')
            ->once()
            ->with(SkeletonInstaller::LOCK_KEY)
            ->andReturn($generators);
    }
}