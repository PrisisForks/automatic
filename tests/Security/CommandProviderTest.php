<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Security\Test;

use Narrowspark\Automatic\Security\Command\AuditCommand;
use Narrowspark\Automatic\Security\CommandProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CommandProviderTest extends TestCase
{
    public function testGetCommands(): void
    {
        $provider = new CommandProvider();

        $commands = $provider->getCommands();

        $this->assertInstanceOf(AuditCommand::class, $commands[0]);
    }
}
