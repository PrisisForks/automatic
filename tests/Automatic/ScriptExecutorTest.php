<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Test;

use Composer\Composer;
use Composer\EventDispatcher\ScriptExecutionException;
use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Narrowspark\Automatic\Common\Contract\Exception\InvalidArgumentException;
use Narrowspark\Automatic\ScriptExecutor;
use Narrowspark\Automatic\ScriptExtender\ScriptExtender;
use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;

/**
 * @internal
 */
final class ScriptExecutorTest extends MockeryTestCase
{
    /**
     * @var \Composer\Composer
     */
    private $composer;

    /**
     * @var \Composer\IO\IOInterface|\Mockery\MockInterface
     */
    private $ioMock;

    /**
     * @var \Composer\Util\ProcessExecutor|\Mockery\MockInterface
     */
    private $processExecutorMock;

    /**
     * @var \Narrowspark\Automatic\ScriptExecutor
     */
    private $scriptExecutor;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->composer            = new Composer();
        $this->ioMock              = $this->mock(IOInterface::class);
        $this->processExecutorMock = $this->mock(ProcessExecutor::class);

        $this->scriptExecutor = new ScriptExecutor($this->composer, $this->ioMock, $this->processExecutorMock, []);
    }

//    public function testAddedExtenderThrowException(): void
//    {
//        $this->expectException(InvalidArgumentException::class);
//        $this->expectExceptionMessage('The class [stdClass] must implement the interface [Narrowspark\Automatic\Common\Contract\ScriptExtender].');
//
//        $this->scriptExecutor->addExtender(\stdClass::class);
//    }
//
//    public function testAddedExtenderThrowExceptionOnExistendExtender(): void
//    {
//        $this->expectException(InvalidArgumentException::class);
//        $this->expectExceptionMessage('Script executor extender with the name [script] already exists.');
//
//        $this->scriptExecutor->addExtender(ScriptExtender::class);
//        $this->scriptExecutor->addExtender(ScriptExtender::class);
//    }

    public function testExecute(): void
    {
        $this->scriptExecutor->addExtender(ScriptExtender::class);

        $this->ioMock->shouldReceive('isDecorated')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('isVerbose')
            ->once()
            ->andReturn(true);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executing script [php -v]', true);

        $this->processExecutorMock->shouldReceive('execute')
            ->once()
            ->with('php -v', \Mockery::type(\Closure::class))
            ->andReturn(0);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executed script [php -v] <info>[OK]</info>');

        $this->scriptExecutor->execute('script', 'php -v');
    }

    public function testExecuteWithCmdError(): void
    {
        $this->expectException(ScriptExecutionException::class);

        $this->scriptExecutor->addExtender(ScriptExtender::class);

        $this->ioMock->shouldReceive('isDecorated')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('isVerbose')
            ->once()
            ->andReturn(true);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executing script [php -v]', true);

        $this->processExecutorMock->shouldReceive('execute')
            ->once()
            ->with('php -v', \Mockery::type(\Closure::class))
            ->andReturn(1);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executed script [php -v] <error>[KO]</error>');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<error>Script [php -v] returned with error code 1</error>');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('!!  ');

        $this->scriptExecutor->execute('script', 'php -v');
    }

    public function testExecuteWithoutVerbose(): void
    {
        $this->scriptExecutor->addExtender(ScriptExtender::class);

        $this->ioMock->shouldReceive('isDecorated')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('isVerbose')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executing script [php -v]', false);

        $this->processExecutorMock->shouldReceive('execute')
            ->once()
            ->with('php -v', \Mockery::type(\Closure::class))
            ->andReturn(0);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<info>[OK]</info>');

        $this->scriptExecutor->execute('script', 'php -v');
    }

    public function testExecuteWithoutVerboseAndCmdError(): void
    {
        $this->expectException(ScriptExecutionException::class);

        $this->scriptExecutor->addExtender(ScriptExtender::class);

        $this->ioMock->shouldReceive('isDecorated')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('isVerbose')
            ->once()
            ->andReturn(false);
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('Executing script [php -v]', false);

        $this->processExecutorMock->shouldReceive('execute')
            ->once()
            ->with('php -v', \Mockery::type(\Closure::class))
            ->andReturn(1);

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<error>[KO]</error>');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<error>Script [php -v] returned with error code 1</error>');
        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('!!  ');

        $this->scriptExecutor->execute('script', 'php -v');
    }

    public function testWithoutExtender(): void
    {
        $this->ioMock->shouldReceive('writeError')
            ->never()
            ->with('Executing script [php -v]', false);

        $this->scriptExecutor->execute('script', 'php -v');
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods($allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}