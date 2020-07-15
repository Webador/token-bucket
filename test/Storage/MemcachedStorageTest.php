<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\MemcachedStorage;
use PHPUnit\Framework\TestCase;

class MemcachedStorageTest extends TestCase
{
    /** @var \Memcached The memcached API. */
    private $memcached;

    /** @var MemcachedStorage The SUT. */
    private $storage;
    
    protected function setUp()
    {
        parent::setUp();

        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('"memcached" extension is not loaded.');
        }
        if (!getenv("MEMCACHE_HOST")) {
            $this->markTestSkipped('"MEMCACHE_HOST" environment variable is not set.');
            return;
        }

        $this->memcached = new \Memcached();
        $this->memcached->addServer(getenv("MEMCACHE_HOST"), 11211);

        $this->storage = new MemcachedStorage("test", $this->memcached);
        $this->storage->bootstrap(123);
    }
    
    protected function tearDown()
    {
        parent::tearDown();
        
        if (!getenv("MEMCACHE_HOST")) {
            return;
        }
        $memcached = new \Memcached();
        $memcached->addServer(getenv("MEMCACHE_HOST"), 11211);
        $memcached->flush();
    }

    /**
     * Tests bootstrap() returns silenty if the key exists already.
     */
    public function testBootstrapReturnsSilentlyIfKeyExists()
    {
        $this->storage->bootstrap(234);
    }

    /**
     * Tests bootstrap() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testBootstrapFails()
    {
        $storage = new MemcachedStorage("test", new \Memcached());
        $storage->bootstrap(123);
    }

    /**
     * Tests isBootstrapped() fails
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testIsBootstrappedFails()
    {
        $this->markTestIncomplete();
    }

    /**
     * Tests remove() fails
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testRemoveFails()
    {
        $storage = new MemcachedStorage("test", new \Memcached());
        $storage->remove();
    }

    /**
     * Tests setMicrotime() fails if getMicrotime() wasn't called first.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testSetMicrotimeFailsIfGetMicrotimeNotCalledFirst()
    {
        $this->storage->setMicrotime(123);
    }

    /**
     * Tests setMicrotime() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testSetMicrotimeFails()
    {
        $this->storage->getMicrotime();
        $this->memcached->resetServerList();
        $this->storage->setMicrotime(123);
    }
    
    /**
     * Tests setMicrotime() returns silenty if the cas operation failed.
     */
    public function testSetMicrotimeReturnsSilentlyIfCASFailed()
    {
        // acquire cas token
        $this->storage->getMicrotime();
        
        // invalidate the cas token
        $storage2 = new MemcachedStorage("test", $this->memcached);
        $storage2->getMicrotime();
        $storage2->setMicrotime(234);
        
        $this->storage->setMicrotime(123);
    }
    
    
    /**
     * Tests getMicrotime() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testGetMicrotimeFails()
    {
        $storage = new MemcachedStorage("test", new \Memcached());
        $storage->getMicrotime();
    }
}
