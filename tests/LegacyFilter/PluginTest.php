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

use Mockery;
use Narrowspark\Automatic\Common\Contract\Container as ContainerContract;
use Narrowspark\Automatic\LegacyFilter\FunctionMock;
use Narrowspark\Automatic\LegacyFilter\Plugin;
use Narrowspark\Automatic\Tests\Helper\AbstractMockeryTestCase;
use Narrowspark\Automatic\Tests\Traits\ArrangeComposerClassesTrait;
use Nyholm\NSA;

/**
 * @internal
 *
 * @covers \Narrowspark\Automatic\LegacyFilter\Plugin
 *
 * @medium
 */
final class PluginTest extends AbstractMockeryTestCase
{
    use ArrangeComposerClassesTrait;

    /** @var \Narrowspark\Automatic\LegacyFilter\Plugin */
    private $plugin;

    /** @var \Mockery\MockInterface|\Narrowspark\Automatic\Common\Contract\Container */
    private $containerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->arrangeComposerClasses();

        $this->plugin = new Plugin();

        $this->containerMock = Mockery::mock(ContainerContract::class);

        NSA::setProperty($this->plugin, 'container', $this->containerMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        FunctionMock::$isOpensslActive = true;
    }

    public function testGetSubscribedEvents(): void
    {
        NSA::setProperty($this->plugin, 'activated', true);

        self::assertCount(1, Plugin::getSubscribedEvents());

        NSA::setProperty($this->plugin, 'activated', false);

        self::assertCount(0, Plugin::getSubscribedEvents());
    }

    public function testActivateWithNoOpenssl(): void
    {
        FunctionMock::$isOpensslActive = false;

        $this->ioMock->shouldReceive('writeError')
            ->once()
            ->with('<warning>Narrowspark Automatic LegacyFilter has been disabled. You must enable the openssl extension in your [php.ini] file</warning>');

        $this->plugin->activate($this->composerMock, $this->ioMock);
    }

    /**
     * {@inheritdoc}
     */
    protected function allowMockingNonExistentMethods(bool $allow = false): void
    {
        parent::allowMockingNonExistentMethods(true);
    }
}
