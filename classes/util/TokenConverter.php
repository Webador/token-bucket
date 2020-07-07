<?php

namespace bandwidthThrottle\tokenBucket\util;

use bandwidthThrottle\tokenBucket\Rate;

/**
 * Can convert tokens to seconds and vice versa given the token's rate.
 */
final class TokenConverter
{
    /** @var Rate */
    private $rate;
    
    /** @var int precision scale for bc_* operations. */
    private $bcScale = 8;

    public function __construct(Rate $rate)
    {
        $this->rate = $rate;
    }
    
    /**
     * Converts a duration of seconds into an amount of tokens.
     *
     * @param float $seconds
     * @return int
     */
    public function convertSecondsToTokens($seconds)
    {
        return (int) ($seconds * $this->rate->getTokensPerSecond());
    }
    
    /**
     * Converts an amount of tokens into a duration of seconds.
     *
     * @param int $tokens
     * @return float
     */
    public function convertTokensToSeconds($tokens)
    {
        return $tokens / $this->rate->getTokensPerSecond();
    }
    
    /**
     * Converts an amount of tokens into a timestamp.
     *
     * @param int $tokens
     * @return float
     */
    public function convertTokensToMicrotime($tokens)
    {
        return microtime(true) - $this->convertTokensToSeconds($tokens);
    }
    
    /**
     * Converts a timestamp into tokens.
     *
     * @param float $microtime
     * @return int
     */
    public function convertMicrotimeToTokens($microtime)
    {
        $delta = bcsub(microtime(true), $microtime, $this->bcScale);
        return $this->convertSecondsToTokens($delta);
    }
}
