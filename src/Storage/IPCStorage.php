<?php

namespace JouwWeb\TokenBucket\Storage;

use malkusch\lock\mutex\Mutex;
use malkusch\lock\mutex\SemaphoreMutex;
use JouwWeb\TokenBucket\Storage\Scope\GlobalScope;
use JouwWeb\TokenBucket\Util\DoublePacker;

/**
 * Shared memory-based storage that can be shared among processes of a single host.
 */
final class IPCStorage implements Storage, GlobalScope
{
    /** @var Mutex */
    private $mutex;
    
    /** @var int $key The System V IPC key. */
    private $key;
    
    /** @var resource The shared memory. */
    private $memory;
    
    /** @var resource The semaphore ID. */
    private $semaphore;
    
    /**
     * @param int $key The System V IPC key.
     * @throws StorageException Thrown when IPC infrastructure initialization fails.
     */
    public function __construct($key)
    {
        $this->key = $key;
        $this->attach();
    }
    
    /**
     * Attaches the shared memory segment.
     *
     * @throws StorageException Could not initialize IPC infrastructure.
     */
    private function attach()
    {
        try {
            $this->semaphore = sem_get($this->key);
            $this->mutex = new SemaphoreMutex($this->semaphore);
        } catch (\InvalidArgumentException $e) {
            throw new StorageException("Could not get semaphore id.", 0, $e);
        }
        
        $this->memory = shm_attach($this->key, 128);
        if (!is_resource($this->memory)) {
            throw new StorageException("Failed to attach to shared memory.");
        }
    }
    
    public function bootstrap($microtime)
    {
        if (is_null($this->memory)) {
            $this->attach();
        }
        $this->setMicrotime($microtime);
    }
    
    public function isBootstrapped()
    {
        return !is_null($this->memory) && shm_has_var($this->memory, 0);
    }
    
    public function remove()
    {
        if (!shm_remove($this->memory)) {
            throw new StorageException("Could not release shared memory.");
        }
        $this->memory = null;

        if (!sem_remove($this->semaphore)) {
            throw new StorageException("Could not remove semaphore.");
        }
        $this->semaphore = null;
    }

    public function setMicrotime($microtime)
    {
        $data = DoublePacker::pack($microtime);
        if (!shm_put_var($this->memory, 0, $data)) {
            throw new StorageException("Could not store in shared memory.");
        }
    }

    public function getMicrotime()
    {
        $data = shm_get_var($this->memory, 0);
        if ($data === false) {
            throw new StorageException("Could not read from shared memory.");
        }
        return DoublePacker::unpack($data);
    }

    public function getMutex()
    {
        return $this->mutex;
    }

    public function letMicrotimeUnchanged()
    {
    }
}
