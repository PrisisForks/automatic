<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Prefetcher;

use Composer\Repository\ComposerRepository as BaseComposerRepository;

/**
 * Ported from symfony flex, see original.
 *
 * @see https://github.com/symfony/flex/blob/master/src/ComposerRepository.php
 *
 * (c) Nicolas Grekas <p@tchwork.com>
 */
class ComposerRepository extends BaseComposerRepository
{
    /**
     * @var null|array
     */
    private $providerFiles;

    /**
     * {@inheritdoc}
     */
    protected function loadProviderListings($data): void
    {
        if ($this->providerFiles !== null) {
            parent::loadProviderListings($data);

            return;
        }

        $data = [$data];

        while ($data) {
            $this->providerFiles = [];

            foreach ($data as $d) {
                $this->loadProviderListings($d);
            }

            $loadingFiles        = $this->providerFiles;
            $this->providerFiles = null;

            $data = [];

            $this->rfs->download($loadingFiles, function (...$args) use (&$data): void {
                $data[] = $this->fetchFile(...$args);
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchFile($filename, $cacheKey = null, $sha256 = null, $storeLastModifiedTime = false): array
    {
        if ($this->providerFiles !== null) {
            $this->providerFiles[] = [$filename, $cacheKey, $sha256, $storeLastModifiedTime];

            return [];
        }

        return parent::fetchFile($filename, $cacheKey, $sha256, $storeLastModifiedTime);
    }
}
