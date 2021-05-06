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

namespace Narrowspark\Automatic\Tests\AutoReview;

use Narrowspark\Automatic\Automatic;
use PHPUnit\Framework\TestCase;
use function explode;
use function file_get_contents;
use function json_decode;
use function sprintf;

/**
 * @internal
 *
 * @coversNothing
 *
 * @group auto-review
 * @group covers-nothing
 *
 * @medium
 */
final class ComposerTest extends TestCase
{
    public function testBranchAlias(): void
    {
        $composerJson = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);

        if (! isset($composerJson['extra']['branch-alias'])) {
            $this->addToAssertionCount(1); // composer.json doesn't contain branch alias, all good!

            return;
        }

        self::assertSame(
            ['dev-master' => $this->convertAppVersionToAliasedVersion(Automatic::VERSION)],
            $composerJson['extra']['branch-alias']
        );
    }

    /**
     * @param string $version
     */
    private function convertAppVersionToAliasedVersion($version): string
    {
        $parts = explode('.', $version, 3);

        return sprintf('%d.%d-dev', $parts[0], $parts[1]);
    }
}
