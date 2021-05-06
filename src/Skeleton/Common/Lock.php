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

namespace Narrowspark\Automatic\Skeleton\Common;

use Composer\Json\JsonFile;
use Exception;
use Narrowspark\Automatic\Skeleton\Common\Contract\Resettable as ResettableContract;
use function array_key_exists;
use function count;
use function is_array;
use function ksort;
use function unlink;

/**
 * This file is automatically generated, dont change this file, otherwise the changes are lost after the next mirror update.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
final class Lock implements ResettableContract
{
    /**
     * Instance of JsonFile.
     *
     * @var \Composer\Json\JsonFile
     */
    private $json;

    /**
     * Array of all lock file data.
     *
     * @var array
     */
    private $lock = [];

    /**
     * Create a new Lock instance.
     */
    public function __construct(string $lockFile)
    {
        $this->json = new JsonFile($lockFile);

        if ($this->json->exists()) {
            $this->read();
        }
    }

    /**
     * Check if key exists in lock file.
     */
    public function has(string $mainKey, ?string $name = null): bool
    {
        $mainCheck = array_key_exists($mainKey, $this->lock);

        if ($name === null) {
            return $mainCheck;
        }

        if ($mainCheck && is_array($this->lock[$mainKey])) {
            return array_key_exists($name, $this->lock[$mainKey]);
        }

        return false;
    }

    /**
     * Add a value to the lock file.
     *
     * @param null|array|string $data
     */
    public function add(string $mainKey, $data): void
    {
        $this->lock[$mainKey] = $data;
    }

    /**
     * Add sub value to the lock file.
     *
     * @param null|array|string $data
     */
    public function addSub(string $mainKey, string $name, $data): void
    {
        if (! array_key_exists($mainKey, $this->lock)) {
            $this->lock[$mainKey] = [];
        }

        $this->lock[$mainKey][$name] = $data;
    }

    /**
     * Get package data found in the lock file.
     */
    public function get(string $mainKey, ?string $name = null): mixed
    {
        if (array_key_exists($mainKey, $this->lock)) {
            if ($name === null) {
                return $this->lock[$mainKey];
            }

            if (is_array($this->lock[$mainKey]) && array_key_exists($name, $this->lock[$mainKey])) {
                return $this->lock[$mainKey][$name];
            }
        }

        return null;
    }

    /**
     * Remove a package from lock file.
     */
    public function remove(string $mainKey, ?string $name = null): void
    {
        if ($name === null) {
            unset($this->lock[$mainKey]);
        }

        if (array_key_exists($mainKey, $this->lock)) {
            unset($this->lock[$mainKey][$name]);
        }
    }

    /**
     * Write a lock file.
     *
     * @throws Exception
     */
    public function write(): void
    {
        ksort($this->lock);

        $this->json->write($this->lock);

        $this->reset();
    }

    /**
     * Read the lock file.
     *
     * @return mixed[][]|mixed[][]|null[]|string[]
     */
    public function read(): array
    {
        if (count($this->lock) === 0) {
            $this->lock = $this->json->read();
        }

        return $this->lock;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->lock = [];
    }

    public function delete(): void
    {
        @unlink($this->json->getPath());
    }
}
