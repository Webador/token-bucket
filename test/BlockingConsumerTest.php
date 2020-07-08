<?php

namespace JouwWeb\TokenBucket\Test;

use JouwWeb\TokenBucket\BlockingConsumer;
use JouwWeb\TokenBucket\Rate;
use JouwWeb\TokenBucket\Storage\SingleProcessStorage;
use JouwWeb\TokenBucket\TokenBucket;

class BlockingConsumerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests comsumption of cumulated tokens.
     */
    public function testConsecutiveConsume()
    {
        $rate = new Rate(10, Rate::SECOND);
        $bucket = new TokenBucket(10, $rate, new SingleProcessStorage());
        $consumer = new BlockingConsumer($bucket);
        $bucket->bootstrap(10);
        $time = microtime(true);

        $consumer->consume(1);
        $consumer->consume(2);
        $consumer->consume(3);
        $consumer->consume(4);
        $this->assertEquals(0, microtime(true) - $time, '', 0.01);
        
        $consumer->consume(1);
        $this->assertEquals(0.1, microtime(true) - $time, '', 0.01);
        
        usleep(300000); // 0.3s = 3 tokens
        $consumer->consume(4);
        $this->assertEquals(0.5, microtime(true) - $time, '', 0.01);
    }
    
    /**
     * Tests consume().
     *
     * @param float $expected
     * @param int $tokens
     * @param Rate $rate
     * @dataProvider provideTestConsume
     */
    public function testConsume(Rate $rate, $tokens, $expected)
    {
        $bucket   = new TokenBucket(1000, $rate, new SingleProcessStorage());
        $consumer = new BlockingConsumer($bucket);
        $bucket->bootstrap();
        
        $time = microtime(true);
        $consumer->consume($tokens);
        $this->assertEquals($expected, microtime(true) - $time, '', 0.01);
    }
    
    /**
     * Returns test cases for testConsume().
     *
     * @return array Test cases.
     */
    public function provideTestConsume()
    {
        return [
            [new Rate(1, Rate::MILLISECOND), 50,  0.05],
            [new Rate(1, Rate::MILLISECOND), 60,  0.06],
            [new Rate(1, Rate::MILLISECOND), 80,  0.075],
            [new Rate(1, Rate::MILLISECOND), 100,  0.1],
        ];
    }
    
    /**
     * Tests consume() won't sleep less than one millisecond.
     */
    public function testMinimumSleep()
    {
        $this->markTestSkipped("usleep()'s inaccuracy can be greater than the unit under test.");

        $rate = new Rate(10, Rate::MILLISECOND);
        $bucket = new TokenBucket(1, $rate, new SingleProcessStorage());
        $bucket->bootstrap();

        $consumer = new BlockingConsumer($bucket);
        $time = microtime(true);
        
        $consumer->consume(1);
        $this->assertEquals(0.001, microtime(true) - $time, 0.0005);
    }
    
    /**
     * consume() should fail after a timeout.
     *
     * @expectedException \JouwWeb\TokenBucket\TimeoutException
     */
    public function testConsumeShouldFailAfterTimeout()
    {
        $rate = new Rate(10, Rate::SECOND);
        $bucket = new TokenBucket(100, $rate, new SingleProcessStorage());
        $bucket->bootstrap(0);
        $consumer = new BlockingConsumer($bucket, 1);
        
        $consumer->consume(15);
    }
    
    /**
     * consume() should not fail before a timeout.
     */
    public function testConsumeShouldNotFailBeforeTimeout()
    {
        $rate = new Rate(10, Rate::SECOND);
        $bucket = new TokenBucket(10, $rate, new SingleProcessStorage());
        $bucket->bootstrap(0);
        $consumer = new BlockingConsumer($bucket, 1);
        
        $consumer->consume(9);
    }
    
    /**
     * consume() should not never time out.
     */
    public function testConsumeWithoutTimeoutShouldNeverFail()
    {
        // Same test as testConsumeShouldFailAfterTimeout() but without timeout
        $rate = new Rate(10, Rate::SECOND);
        $bucket = new TokenBucket(100, $rate, new SingleProcessStorage());
        $bucket->bootstrap(0);
        $consumer = new BlockingConsumer($bucket);

        $consumer->consume(15);
    }
}
