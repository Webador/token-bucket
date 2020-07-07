<?php

namespace bandwidthThrottle\tokenBucket;

use bandwidthThrottle\tokenBucket\storage\StorageException;

/**
 * A {@see TokenBucket} consumer that will halt execution until the requested amount of tokens becomes available.
 */
final class BlockingConsumer
{
    /** @var TokenBucket The bucket to consume from. */
    private $bucket;
    
    /** @var int|null Optional timeout in seconds. */
    private $timeout;

    /**
     * @param TokenBucket $bucket The bucket to consume from.
     * @param int|null $timeout Optional timeout in seconds.
     */
    public function __construct(TokenBucket $bucket, $timeout = null)
    {
        $this->bucket = $bucket;

        if ($timeout < 0) {
            throw new \InvalidArgumentException("Timeout must be null or positive");
        }
        $this->timeout = $timeout;
    }
    
    /**
     * Consumes tokens form the underlying {@see TokenBucket}. If it doesn't have enough tokens, script execution will
     * be halted (up till timeout if set) until they can be consumed.
     *
     * @param int $tokens The token amount.
     *
     * @throws \LengthException The token amount is larger than the bucket's capacity.
     * @throws StorageException The stored microtime could not be accessed.
     * @throws TimeoutException The timeout was exceeded.
     */
    public function consume($tokens)
    {
        $timedOut = is_null($this->timeout) ? null : (microtime(true) + $this->timeout);
        while (!$this->bucket->consume($tokens, $seconds)) {
            self::throwTimeoutIfExceeded($timedOut);
            $seconds = self::keepSecondsWithinTimeout($seconds, $timedOut);
            
            // avoid an overflow before converting $seconds into microseconds.
            if ($seconds > 1) {
                // leave more than one second to avoid sleeping the minimum of one millisecond.
                $sleepSeconds = ((int) $seconds) - 1;

                sleep($sleepSeconds);
                $seconds -= $sleepSeconds;
            }

            // sleep at least 1 millisecond.
            usleep(max(1000, $seconds * 1000000));
        }
    }
    
    /**
     * @param float|null $timedOut
     * @throws TimeoutException
     */
    private static function throwTimeoutIfExceeded($timedOut)
    {
        if (is_null($timedOut)) {
            return;
        }
        if (time() >= $timedOut) {
            throw new TimeoutException("Timed out");
        }
    }
    
    /**
     * Adjusts the wait seconds to be within the timeout.
     *
     * @param float $seconds
     * @param float|null $timedOut
     * @return float
     */
    private static function keepSecondsWithinTimeout($seconds, $timedOut)
    {
        if (is_null($timedOut)) {
            return $seconds;
        }
        $remainingSeconds = max($timedOut - microtime(true), 0);
        return min($remainingSeconds, $seconds);
    }
}
