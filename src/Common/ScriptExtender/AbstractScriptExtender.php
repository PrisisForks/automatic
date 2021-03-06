<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\ScriptExtender;

use Composer\Composer;
use Composer\IO\IOInterface;
use Narrowspark\Automatic\Common\Contract\ScriptExtender as ScriptExtenderContract;

abstract class AbstractScriptExtender implements ScriptExtenderContract
{
    /**
     * The composer instance.
     *
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * The composer io implementation.
     *
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * The composer extra options data.
     *
     * @var array
     */
    protected $options;

    /**
     * Create a new script extender instance.
     *
     * @param \Composer\Composer       $composer
     * @param \Composer\IO\IOInterface $io
     * @param array                    $options
     */
    public function __construct(Composer $composer, IOInterface $io, array $options = [])
    {
        $this->composer = $composer;
        $this->io       = $io;
        $this->options  = $options;
    }
}
