<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\PredisStorage;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\ClientException;

class PredisStorageTest extends TestCase
{
    /** @var Client The API. */
    private $redis;

    /** @var PredisStorage The SUT. */
    private $storage;
    
    protected function setUp()
    {
        parent::setUp();
        
        if (!getenv("REDIS_URI")) {
            $this->markTestSkipped();
        }
        $this->redis   = new Client(getenv("REDIS_URI"));
        $this->storage = new PredisStorage("test", $this->redis);
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
        $redis = $this->createMock(Client::class);
        $redis->expects($this->once())->method("__call")
                ->willThrowException(new ClientException());
        $storage = new PredisStorage("test", $redis);
        call_user_func($method, $storage);
    }

    /**
     * Provides test cases for testBrokenCommunication().
     *
     * @return array Testcases.
     */
    public function provideTestBrokenCommunication()
    {
        return [
            [function (PredisStorage $storage) {
                $storage->bootstrap(1);
            }],
            [function (PredisStorage $storage) {
                $storage->isBootstrapped();
            }],
            [function (PredisStorage $storage) {
                $storage->remove();
            }],
            [function (PredisStorage $storage) {
                $storage->setMicrotime(1);
            }],
            [function (PredisStorage $storage) {
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
        $redis = $this->createMock(Client::class);
        $redis->expects($this->once())->method("__call")
                ->with("set")
                ->willReturn(false);
        $storage = new PredisStorage("test", $redis);
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
