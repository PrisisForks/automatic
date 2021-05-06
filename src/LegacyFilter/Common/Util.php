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

namespace Narrowspark\Automatic\LegacyFilter\Common;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Exception;
use InvalidArgumentException;
use Narrowspark\Automatic\LegacyFilter\Common\Contract\Exception\RuntimeException;
use function file_get_contents;
use function preg_match;
use function substr;

final class Util
{
    /**
     * Private constructor; non-instantiable.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Return the composer json file and json manipulator.
     *
     * @throws InvalidArgumentException
     *
     * @return array{0: \Composer\Json\JsonFile, 1: \Composer\Json\JsonManipulator}
     */
    public static function getComposerJsonFileAndManipulator(): array
    {
        $json = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(file_get_contents($json->getPath()));

        return [$json, $manipulator];
    }

    /**
     * Get the composer.lock file path.
     */
    public static function getComposerLockFile(): string
    {
        return substr(Factory::getComposerFile(), 0, -4) . 'lock';
    }

    /**
     * Get the composer version.
     *
     * @throws \Narrowspark\Automatic\LegacyFilter\Common\Contract\Exception\RuntimeException
     */
    public static function getComposerVersion(): string
    {
        preg_match('/\d+.\d+.\d+/m', Composer::VERSION, $versionMatches);

        if ($versionMatches !== null) {
            return $versionMatches[0];
        }

        preg_match('/\d+.\d+.\d+/m', Composer::BRANCH_ALIAS_VERSION, $branchAliasMatches);

        if ($branchAliasMatches !== null) {
            return $branchAliasMatches[0];
        }

        throw new RuntimeException('No composer version found.');
    }

    /**
     * Update composer.lock file with the composer.json change.
     *
     * @throws Exception
     */
    public static function updateComposerLock(Composer $composer, IOInterface $io): void
    {
        $composerLockPath = Util::getComposerLockFile();
        $composerJson = file_get_contents(Factory::getComposerFile());

        $lockFile = new JsonFile($composerLockPath, null, $io);
        $locker = new Locker(
            $io,
            $lockFile,
            $composer->getRepositoryManager(),
            $composer->getInstallationManager(),
            (string) $composerJson
        );

        $lockData = $locker->getLockData();
        $lockData['content-hash'] = Locker::getContentHash((string) $composerJson);

        $lockFile->write($lockData);
    }
}
