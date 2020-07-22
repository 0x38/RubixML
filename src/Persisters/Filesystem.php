<?php

namespace Rubix\ML\Persisters;

use Rubix\ML\Encoding;
use Rubix\ML\Persistable;
use Rubix\ML\Other\Helpers\Params;
use Rubix\ML\Persisters\Serializers\Native;
use Rubix\ML\Persisters\Serializers\Serializer;
use InvalidArgumentException;
use RuntimeException;
use Stringable;

/**
 * Filesystem
 *
 * Filesystems are local or remote storage drives that are organized by files
 * and folders. The filesystem persister serializes models to a file at a
 * user-specified path.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Filesystem implements Persister, Stringable
{
    /**
     * The extension to give files created as part of a persistable's save history.
     *
     * @var string
     */
    public const HISTORY_EXT = 'old';

    /**
     * The path to the model file on the filesystem.
     *
     * @var string
     */
    protected $path;

    /**
     * Should we keep a history of past saves?
     *
     * @var bool
     */
    protected $history;

    /**
     * The serializer used to convert to and from serial format.
     *
     * @var \Rubix\ML\Persisters\Serializers\Serializer
     */
    protected $serializer;

    /**
     * @param string $path
     * @param bool $history
     * @param \Rubix\ML\Persisters\Serializers\Serializer|null $serializer
     * @throws \InvalidArgumentException
     */
    public function __construct(string $path, bool $history = false, ?Serializer $serializer = null)
    {
        if (!is_writable(dirname($path))) {
            throw new InvalidArgumentException('Folder does not exist or'
                . ' is not writable, check path and permissions.');
        }

        if (is_file($path) and !is_writable($path)) {
            throw new InvalidArgumentException("Preexisting file {$path} is not writable.");
        }

        $this->path = $path;
        $this->history = $history;
        $this->serializer = $serializer ?? new Native();
    }

    /**
     * Save the persistable model.
     *
     * @param \Rubix\ML\Persistable $persistable
     * @throws \RuntimeException
     */
    public function save(Persistable $persistable) : void
    {
        if ($this->history and is_file($this->path)) {
            $timestamp = (string) time();

            $filename = $this->path . '-' . $timestamp . '.' . self::HISTORY_EXT;

            $num = 0;

            while (file_exists($filename)) {
                $filename = $this->path . '-' . $timestamp . '-' . ++$num . '.' . self::HISTORY_EXT;
            }

            if (!rename($this->path, $filename)) {
                throw new RuntimeException('Failed to create history file.');
            }
        }

        $this->serializer->serialize($persistable)->write($this->path);
    }

    /**
     * Load the last model that was saved.
     *
     * @throws \RuntimeException
     * @return \Rubix\ML\Persistable
     */
    public function load() : Persistable
    {
        if (!is_readable($this->path)) {
            throw new RuntimeException("File {$this->path} is not readable.");
        }

        $data = new Encoding(file_get_contents($this->path) ?: '');

        if ($data->bytes() === 0) {
            throw new RuntimeException("File {$this->path} does not"
                . ' contain any data.');
        }

        return $this->serializer->unserialize($data);
    }

    /**
     * Return the string representation of the object.
     *
     * @return string
     */
    public function __toString() : string
    {
        return "Filesystem {path: {$this->path},"
            . ' history: ' . Params::toString($this->history) . ','
            . " serializer: {$this->serializer}}";
    }
}
