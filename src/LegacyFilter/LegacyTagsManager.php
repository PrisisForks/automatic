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

namespace Narrowspark\Automatic\LegacyFilter;

use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\RootPackageInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Narrowspark\Automatic\Common\Downloader\Downloader;
use Narrowspark\Automatic\LegacyFilter\Contract\LegacyTagsManager as LegacyTagsManagerContract;
use function array_intersect;
use function array_key_exists;
use function explode;
use function sprintf;
use function strpos;
use function substr;
use function Webmozart\Assert\Tests\StaticAnalysis\null;

final class LegacyTagsManager implements LegacyTagsManagerContract
{
    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * A version parser instance.
     *
     * @var \Composer\Semver\VersionParser
     */
    private $versionParser;

    /**
     * A list of the legacy tags versions and constraints.
     *
     * @var array<string, array<string, ConstraintInterface|string>>
     * @psalm-var array<string, array{ version: string, constrain: ConstraintInterface|Constraint|MultiConstraint }>
     */
    private $legacyTags = [];

    /** @var null|\Narrowspark\Automatic\Common\Downloader\Downloader */
    private $downloader;

    /** @var null|array */
    private $versions;

    public function __construct(IOInterface $io, Downloader $downloader)
    {
        $this->io = $io;
        $this->downloader = $downloader;
        $this->versionParser = new VersionParser();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraint(string $name, string $require): void
    {
        $this->legacyTags[$name] = [
            'version' => $require,
            'constrain' => $this->versionParser->parseConstraints($require),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function hasProvider(string $file): bool
    {
        foreach ($this->legacyTags as $name => $constraint) {
            [$namespace,] = explode('/', $name, 2);

            if (strpos($file, sprintf('provider-%s$', $namespace)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function removeLegacyTags(array $data, RootPackageInterface $rootPackage, array $lockedPackages): array
    {
        if (empty($data)) {
            return $data;
        }

        if ($this->versions === null && $this->downloader !== null && array_key_exists('symfony/symfony', $this->legacyTags)) {
            /** @var Constraint|MultiConstraint $symfonyConstraint */
            $symfonyConstraint = $this->legacyTags['symfony/symfony']['constrain'];

            $this->versions = $this->getVersions(
                $this->downloader->get('/versions.json')->getBody() ?? [],
                $symfonyConstraint
            );
            $this->downloader = null;

            /** @var string $symfonyVersion */
            $symfonyVersion = $this->legacyTags['symfony/symfony']['version'];

            foreach ($this->versions['splits'] ?? [] as $name => $versions) {
                $this->addConstraint($name, $symfonyVersion);
            }
        }

        $lockedVersions = [];

        foreach ($lockedPackages as $package) {
            $lockedVersions[$package->getName()] = [$package->getVersion()];

            if ($package instanceof AliasPackage) {
                $lockedVersions[$package->getName()][] = $package->getAliasOf()->getVersion();
            }
        }

        $rootConstraints = [];

        foreach ($rootPackage->getRequires() + $rootPackage->getDevRequires() as $name => $link) {
            $rootConstraints[$name] = $link->getConstraint();
        }

        $filteredPackages = [];
        $logs = [];

        foreach ($data as $package) {
            $name = $package->getName();
            $version = $package->getVersion();
            $versions = [$version];

            if ($package instanceof AliasPackage) {
                $versions[] = $package->getAliasOf()->getVersion();
            }

            if (! isset($this->legacyTags[$name]) || array_intersect($versions, $lockedVersions[$name] ?? []) || (isset($rootConstraints[$name]) && ! Intervals::haveIntersections($this->legacyTags[$name]['constrain'], $rootConstraints[$name]))) {
                $filteredPackages[] = $package;

                continue;
            }

            if (isset($package->getExtra()['branch-alias'])) {
                $branchAliases = $package->getExtra()['branch-alias'];

                if (
                    (isset($branchAliases[$version]) && $alias = $branchAliases[$version])
                    || (isset($branchAliases['dev-main']) && $alias = $branchAliases['dev-main'])
                    || (isset($branchAliases['dev-trunk']) && $alias = $branchAliases['dev-trunk'])
                    || (isset($branchAliases['dev-develop']) && $alias = $branchAliases['dev-develop'])
                    || (isset($branchAliases['dev-default']) && $alias = $branchAliases['dev-default'])
                    || (isset($branchAliases['dev-latest']) && $alias = $branchAliases['dev-latest'])
                    || (isset($branchAliases['dev-next']) && $alias = $branchAliases['dev-next'])
                    || (isset($branchAliases['dev-current']) && $alias = $branchAliases['dev-current'])
                    || (isset($branchAliases['dev-support']) && $alias = $branchAliases['dev-support'])
                    || (isset($branchAliases['dev-tip']) && $alias = $branchAliases['dev-tip'])
                    || (isset($branchAliases['dev-master']) && $alias = $branchAliases['dev-master'])
                ) {
                    $versions[] = $this->versionParser->normalize($alias);
                }
            }

            foreach ($versions as $version) {
                if (isset($this->legacyTags[$name]) && $this->legacyTags[$name]['constrain']->matches(new Constraint('==', $version))) {
                    $filteredPackages[] = $package;

                    continue 2;
                }
            }

            $logs[$name] = $this->legacyTags[$name]['version'];
        }

        if ($this->io !== null) {
            $this->io->writeError('');

            foreach ($logs as $name => $version) {
                $this->io->writeError(sprintf('<info>Restricting packages listed in [%s] to [%s].</info>', $name, $version));
            }

            $this->io->writeError('');
        }

        return $filteredPackages;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->legacyTags = [];
    }

    /**
     * @param array<string, array<string, array<int, string>>> $versions
     * @param Constraint|MultiConstraint                       $symfonyConstraint
     */
    private function getVersions(array $versions, $symfonyConstraint): array
    {
        $okVersions = [];

        foreach ($versions['splits'] as $name => $vers) {
            foreach ($vers as $i => $v) {
                if (! isset($okVersions[$v])) {
                    $okVersions[$v] = false;
                    $w = substr($v, -2) === '.x' ? $versions['next'] : $v;

                    for ($j = 0; $j < 60; $j++) {
                        if ($symfonyConstraint->matches(new Constraint('==', $w . '.' . $j . '.0'))) {
                            $okVersions[$v] = true;

                            break;
                        }
                    }
                }

                if (! $okVersions[$v]) {
                    unset($vers[$i]);
                }
            }

            if ($versions['splits'][$name] === $vers || empty($vers)) {
                unset($versions['splits'][$name]);
            }
        }

        return $versions;
    }
}
