<?php

namespace bandwidthThrottle\tokenBucket;

/**
 * Defines a token production rate for a specified unit of time. E.g., `new Rate(100, Rate::SECOND)` will produce 100
 * tokens per second.
 */
final class Rate
{
    const MICROSECOND = "microsecond";
    const MILLISECOND = "millisecond";
    const SECOND = "second";
    const MINUTE = "minute";
    const HOUR = "hour";
    const DAY = "day";
    const WEEK = "week";
    const MONTH = "month";
    const YEAR = "year";

    /** @var float[] Mapping from unit to seconds. */
    private static $unitMap = [
        self::MICROSECOND => 0.000001,
        self::MILLISECOND => 0.001,
        self::SECOND => 1,
        self::MINUTE => 60,
        self::HOUR => 3600,
        self::DAY => 86400,
        self::WEEK => 604800,
        self::MONTH => 2629743.83,
        self::YEAR => 31556926,
    ];
    
    /** @var int Amount of tokens to produce per unit. */
    private $tokens;

    /** @var string Unit as one of this class's constants. */
    private $unit;
    
    /**
     * @param int $tokens Amount of tokens to produce per unit.
     * @param string $unit Unit as one of this class's constants.
     */
    public function __construct($tokens, $unit)
    {
        if (!isset(self::$unitMap[$unit])) {
            throw new \InvalidArgumentException("Not a valid unit.");
        }
        if ($tokens <= 0) {
            throw new \InvalidArgumentException("Amount of tokens should be greater then 0.");
        }
        $this->tokens = $tokens;
        $this->unit   = $unit;
    }

    /**
     * Returns the rate in Tokens per second.
     *
     * @return float
     */
    public function getTokensPerSecond()
    {
        return $this->tokens / self::$unitMap[$this->unit];
    }
}
