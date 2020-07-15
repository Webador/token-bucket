<?php

namespace JouwWeb\TokenBucket\Test;

use JouwWeb\TokenBucket\BlockingConsumer;
use JouwWeb\TokenBucket\Rate;
use JouwWeb\TokenBucket\Storage\SingleProcessStorage;
use JouwWeb\TokenBucket\TokenBucket;
use JouwWeb\TokenBucket\Util\TokenConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;

class BlockingConsumerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        ClockMock::register(__CLASS__);
        ClockMock::register(BlockingConsumer::class);
        ClockMock::register(TokenConverter::class);
        ClockMock::register(TokenBucket::class);
        ClockMock::withClockMock(true);
    }

    public function tearDown(): void
    {
        ClockMock::withClockMock(false);
    }

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

        $this->assertEquals(0, bcsub(microtime(true), $time, 3));
        
        $consumer->consume(1);
        $this->assertEquals(0.1, bcsub(microtime(true), $time, 3));
        
        usleep(300000); // 0.3s = 3 tokens
        $consumer->consume(4);
        $this->assertEquals(0.5, bcsub(microtime(true), $time, 3));
    }
    
    /**
     * Tests consume().
     *
     * @dataProvider provideTestConsume
     */
    public function testConsume(Rate $rate, int $tokens, float $expected)
    {
        $bucket = new TokenBucket(1000, $rate, new SingleProcessStorage());
        $consumer = new BlockingConsumer($bucket);
        $bucket->bootstrap();
        
        $time = microtime(true);
        $consumer->consume($tokens);
        $this->assertEquals($expected, bcsub(microtime(true), $time, 3));
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
            [new Rate(1, Rate::MILLISECOND), 75,  0.075],
            [new Rate(1, Rate::MILLISECOND), 100,  0.1],
        ];
    }
    
    /**
     * Tests consume() won't sleep less than one millisecond.
     */
    public function testMinimumSleep()
    {
        $rate = new Rate(10, Rate::MILLISECOND);
        $bucket = new TokenBucket(1, $rate, new SingleProcessStorage());
        $bucket->bootstrap();

        $consumer = new BlockingConsumer($bucket);
        $time = microtime(true);
        
        $consumer->consume(1);
        $this->assertEquals(0.001, bcsub(microtime(true), $time, 3));
    }
    
    /**
     * consume() should fail after a timeout.
     *
     * @expectedException \JouwWeb\TokenBucket\TimeoutException
     */
    public function testConsumeShouldFailAfterTimeout()
    {
        $rate = new Rate(0.1, Rate::SECOND);
        $bucket = new TokenBucket(1, $rate, new SingleProcessStorage());
        $bucket->bootstrap(0);
        $consumer = new BlockingConsumer($bucket, 9);

        $consumer->consume(1);
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
        
        $consumer->consume(10);
        $this->addToAssertionCount(1);
    }
    
    /**
     * consume() without timeout should never time out.
     */
    public function testConsumeWithoutTimeoutShouldNeverFail()
    {
        $rate = new Rate(0.1, Rate::YEAR);
        $bucket = new TokenBucket(1, $rate, new SingleProcessStorage());
        $bucket->bootstrap(0);
        $consumer = new BlockingConsumer($bucket);

        $consumer->consume(1);
        $this->addToAssertionCount(1);
    }
}
