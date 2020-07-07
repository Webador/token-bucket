<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\PHPRedisStorage;

/**
 * These tests need the environment variable REDIS_URI.
 */
class PHPRedisStorageTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Redis The API. */
    private $redis;

    /** @var PHPRedisStorage The SUT. */
    private $storage;
    
    protected function setUp()
    {
        parent::setUp();
        
        if (!getenv("REDIS_URI")) {
            $this->markTestSkipped();
        }
        $uri = parse_url(getenv("REDIS_URI"));
        $this->redis = new \Redis();
        $this->redis->connect($uri["host"]);
        
        $this->storage = new PHPRedisStorage("test", $this->redis);
    }
    
    /**
     * Tests broken server communication.
     *
     * @param callable $method The tested method.
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     * @dataProvider provideTestBrokenCommunication
     */
    public function testBrokenCommunication(callable $method)
    {
        $this->redis->close();
        call_user_func($method, $this->storage);
    }

    /**
     * Provides test cases for testBrokenCommunication().
     *
     * @return array Testcases.
     */
    public function provideTestBrokenCommunication()
    {
        return [
            [function (PHPRedisStorage $storage) {
                $storage->bootstrap(1);
            }],
            [function (PHPRedisStorage $storage) {
                $storage->isBootstrapped();
            }],
            [function (PHPRedisStorage $storage) {
                $storage->remove();
            }],
            [function (PHPRedisStorage $storage) {
                $storage->setMicrotime(1);
            }],
            [function (PHPRedisStorage $storage) {
                $storage->getMicrotime();
            }],
        ];
    }
    
    /**
     * Tests remove() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testRemoveFails()
    {
        $this->storage->bootstrap(1);
        $this->storage->remove();

        $this->storage->remove();
    }
    
    /**
     * Tests setMicrotime() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testSetMicrotimeFails()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method("set")
                ->willReturn(false);
        $storage = new PHPRedisStorage("test", $redis);
        $storage->setMicrotime(1);
    }
    
    /**
     * Tests getMicrotime() fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testGetMicrotimeFails()
    {
        $this->storage->bootstrap(1);
        $this->storage->remove();

        $this->storage->getMicrotime();
    }
}
