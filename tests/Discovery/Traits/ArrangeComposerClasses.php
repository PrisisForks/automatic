<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Test\Traits;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Narrowspark\Discovery\Lock;
use Symfony\Component\Console\Input\InputInterface;

trait ArrangeComposerClasses
{
    /**
     * @var \Composer\Composer|\Mockery\MockInterface
     */
    protected $composerMock;

    /**
     * @var \Composer\Config|\Mockery\MockInterface
     */
    protected $configMock;

    /**
     * @var \Mockery\MockInterface|\Symfony\Component\Console\Input\InputInterface
     */
    protected $inputMock;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    protected $ioMock;

    /**
     * @var string
     */
    protected $composerCachePath;

    /**
     * @var \Mockery\MockInterface|\Narrowspark\Discovery\Lock
     */
    private $lockMock;

    protected function arrangeComposerClasses(): void
    {
        $this->composerMock = $this->mock(Composer::class);
        $this->configMock   = $this->mock(Config::class);
        $this->ioMock       = $this->mock(IOInterface::class);
        $this->inputMock    = $this->mock(InputInterface::class);
        $this->lockMock     = $this->mock(Lock::class);
    }

    protected function arrangePackagist(): void
    {
        $this->ioMock->shouldReceive('hasAuthentication')
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->with('Downloading https://packagist.org/packages.json', true, IOInterface::DEBUG);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Writing ' . $this->composerCachePath . '/repo/https---packagist.org/packages.json into cache', true, IOInterface::DEBUG);
    }
}