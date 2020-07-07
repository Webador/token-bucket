<?php

namespace JouwWeb\TokenBucket\Storage;

use JouwWeb\TokenBucket\Storage\Scope\GlobalScope;
use malkusch\lock\mutex\MemcacheMutex;

/**
 * Memcache based storage which can be shared among processes.
 */
final class MemcacheStorage implements Storage, GlobalScope
{
    const PREFIX = "TokenBucket_";

    /** @var \Memcache The connected memcache API.*/
    private $memcache;
    
    /** @var string The key for the token bucket. */
    private $key;
    
    /** @var MemcacheMutex The mutex for this storage. */
    private $mutex;

    
    /**
     * Sets the connected memcache API and the token bucket name. The API needs to be connected already. I.e.,
     * Memcache::connect() was already called.
     *
     * @param string $name The name of the shared token bucket.
     * @param \Memcache $memcache The connected memcache API.
     */
    public function __construct($name, \Memcache $memcache)
    {
        trigger_error("MemcacheStorage has been deprecated in favour of MemcachedStorage.", E_USER_DEPRECATED);
        
        $this->memcache = $memcache;
        $this->key      = self::PREFIX . $name;
        $this->mutex    = new MemcacheMutex($name, $memcache);
    }

    public function bootstrap($microtime)
    {
        $this->setMicrotime($microtime);
    }
    
    public function isBootstrapped()
    {
        return $this->memcache->get($this->key) !== false;
    }
    
    public function remove()
    {
        if (!$this->memcache->delete($this->key)) {
            throw new StorageException("Could not remove microtime.");
        }
    }
    
    public function setMicrotime($microtime)
    {
        if (!$this->memcache->set($this->key, $microtime, 0, 0)) {
            throw new StorageException("Could not set microtime.");
        }
    }

    public function getMicrotime()
    {
        $microtime = $this->memcache->get($this->key);
        if ($microtime === false) {
            throw new StorageException("The key '$this->key' was not found.");
        }
        return (double) $microtime;
    }

    public function getMutex()
    {
        return $this->mutex;
    }

    public function letMicrotimeUnchanged()
    {
    }
}
