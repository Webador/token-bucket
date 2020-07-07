<?php

namespace JouwWeb\TokenBucket\Storage;

use malkusch\lock\mutex\FlockMutex;
use JouwWeb\TokenBucket\Storage\Scope\GlobalScope;
use JouwWeb\TokenBucket\Util\DoublePacker;

final class FileStorage implements Storage, GlobalScope
{
    /** @var FlockMutex */
    private $mutex;
    
    /** @var resource File handle. */
    private $fileHandle;
    
    /** @var string File path. */
    private $path;
    
    /**
     * Sets and opens the file to write to and read from. The file will be created if it does not exist yet. This is an
     * atomic operation.
     *
     * @param string $path
     * @throws StorageException When the file cannot be opened.
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->open();
    }
    
    /**
     * @throws StorageException
     */
    private function open()
    {
        $this->fileHandle = fopen($this->path, "c+");
        if (!is_resource($this->fileHandle)) {
            throw new StorageException("Could not open '$this->path'.");
        }
        $this->mutex = new FlockMutex($this->fileHandle);
    }
    
    public function __destruct()
    {
        fclose($this->fileHandle);
    }
    
    public function isBootstrapped()
    {
        $stats = fstat($this->fileHandle);
        return $stats["size"] > 0;
    }
    
    public function bootstrap($microtime)
    {
        $this->open(); // remove() could have deleted the file.
        $this->setMicrotime($microtime);
    }

    /**
     * @throws StorageException
     */
    public function remove()
    {
        // Truncate to notify isBootstrapped() about the new state.
        if (!ftruncate($this->fileHandle, 0)) {
            throw new StorageException("Could not truncate $this->path");
        }
        if (!unlink($this->path)) {
            throw new StorageException("Could not delete $this->path");
        }
    }

    /**
     * @param float $microtime
     * @throws StorageException
     */
    public function setMicrotime($microtime)
    {
        if (fseek($this->fileHandle, 0) !== 0) {
            throw new StorageException("Could not move to beginning of the file.");
        }
        
        $data = DoublePacker::pack($microtime);
        $result = fwrite($this->fileHandle, $data, strlen($data));
        if ($result !== strlen($data)) {
            throw new StorageException("Could not write to storage.");
        }
    }

    /**
     * @return float
     * @throws StorageException
     */
    public function getMicrotime()
    {
        if (fseek($this->fileHandle, 0) !== 0) {
            throw new StorageException("Could not move to beginning of the file.");
        }
        $data = fread($this->fileHandle, 8);
        if ($data === false) {
            throw new StorageException("Could not read from storage.");
        }
        
        return DoublePacker::unpack($data);
    }

    /**
     * @return FlockMutex
     */
    public function getMutex()
    {
        return $this->mutex;
    }

    public function letMicrotimeUnchanged()
    {
    }
}
