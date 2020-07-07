<?php

namespace bandwidthThrottle\tokenBucket;

use malkusch\lock\exception\MutexException;
use bandwidthThrottle\tokenBucket\storage\Storage;
use bandwidthThrottle\tokenBucket\storage\StorageException;
use bandwidthThrottle\tokenBucket\util\TokenConverter;

/**
 * An implementation of the token bucket algorithm that can be used for controlling the usage rate of a resource.
 */
final class TokenBucket
{
    /** @var Rate The rate. */
    private $rate;
    
    /** @var int Token capacity of this bucket. */
    private $capacity;
    
    /** @var Storage The storage. */
    private $storage;
    
    /** @var TokenConverter Token converter. */
    private $tokenConverter;
    
    /**
     * @param int $capacity Token capacity of the bucket. There will never be more tokens available than this number.
     * @param Rate $rate Rate at which tokens become available in the bucket.
     * @param Storage $storage Backing storage for the tokens. This determines the scope of the bucket.
     */
    public function __construct($capacity, Rate $rate, Storage $storage)
    {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException("Capacity should be greater than 0.");
        }

        $this->capacity = $capacity;
        $this->rate  = $rate;
        $this->storage = $storage;

        $this->tokenConverter = new TokenConverter($rate);
    }
    
    /**
     * Bootstraps the storage with an initial amount of tokens. If the storage was already bootstrapped this method
     * returns silently. You should not bootstrap on each request to prevent unnecesarry storage communication. Call
     * this in your bootstrap or deploy process.
     *
     * @param int $tokens Initial amount of tokens, default is 0.
     * @throws StorageException Bootstrapping failed.
     * @throws \LengthException The initial amount of tokens is larger than the capacity.
     */
    public function bootstrap($tokens = 0)
    {
        try {
            if ($tokens > $this->capacity) {
                throw new \LengthException(
                    "Initial token amount ($tokens) is larger than the capacity ($this->capacity)."
                );
            }
            if ($tokens < 0) {
                throw new \InvalidArgumentException(
                    "Initial token amount ($tokens) should be greater than 0."
                );
            }
            
            $this->storage->getMutex()
                ->check(function () {
                    return !$this->storage->isBootstrapped();
                })
                ->then(function () use ($tokens) {
                    $this->storage->bootstrap($this->tokenConverter->convertTokensToMicrotime($tokens));
                });
        } catch (MutexException $e) {
            throw new StorageException("Could not lock bootstrapping", 0, $e);
        }
    }

    /**
     * Consumes tokens from the bucket if there are sufficient tokens available. If there aren't enough tokens
     * available, no tokens will be removed and the remaining seconds to wait are written to $seconds.
     *
     * @param int $tokens Amount of tokens to consume.
     * @param float $seconds The seconds to wait.
     * @return bool Whether tokens were consumed.
     * @throws \LengthException The token amount is larger than the capacity.
     * @throws StorageException The stored microtime could not be accessed.
     */
    public function consume($tokens, &$seconds = 0)
    {
        try {
            if ($tokens > $this->capacity) {
                throw new \LengthException("Token amount ($tokens) is larger than the capacity ($this->capacity).");
            }
            if ($tokens <= 0) {
                throw new \InvalidArgumentException("Token amount ($tokens) should be greater than 0.");
            }

            return $this->storage->getMutex()->synchronized(
                function () use ($tokens, &$seconds) {
                    $tokensAndMicrotime = $this->loadTokensAndTimestamp();
                    $microtime = $tokensAndMicrotime["microtime"];
                    $availableTokens = $tokensAndMicrotime["tokens"];

                    $delta = $availableTokens - $tokens;
                    if ($delta < 0) {
                        $this->storage->letMicrotimeUnchanged();
                        $passed  = microtime(true) - $microtime;
                        $seconds = max(0, $this->tokenConverter->convertTokensToSeconds($tokens) - $passed);
                        return false;
                    } else {
                        $microtime += $this->tokenConverter->convertTokensToSeconds($tokens);
                        $this->storage->setMicrotime($microtime);
                        $seconds = 0;
                        return true;
                    }
                }
            );
        } catch (MutexException $e) {
            throw new StorageException("Could not lock token consumption.", 0, $e);
        }
    }

    /**
     * @return Rate
     */
    public function getRate()
    {
        return $this->rate;
    }
    
    /**
     * @return int
     */
    public function getCapacity()
    {
        return $this->capacity;
    }

    /**
     * Returns the amount of tokens currently available in this bucket without consuming them.
     *
     * @return int
     * @throws StorageException The stored microtime could not be accessed.
     */
    public function getTokens()
    {
        return $this->loadTokensAndTimestamp()["tokens"];
    }
    
    /**
     * Loads the stored timestamp and its respective amount of tokens.
     *
     * @throws StorageException The stored microtime could not be accessed.
     * @return array tokens and microtime
     */
    private function loadTokensAndTimestamp()
    {
        $microtime = $this->storage->getMicrotime();
        
        // Drop overflowing tokens
        $minMicrotime = $this->tokenConverter->convertTokensToMicrotime($this->capacity);
        if ($minMicrotime > $microtime) {
            $microtime = $minMicrotime;
        }
        
        $tokens = $this->tokenConverter->convertMicrotimeToTokens($microtime);
        return [
            "tokens" => $tokens,
            "microtime" => $microtime
        ];
    }
}
