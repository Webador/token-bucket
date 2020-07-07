<?php

namespace bandwidthThrottle\tokenBucket\util;

use bandwidthThrottle\tokenBucket\storage\StorageException;

/**
 * Can pack and unpack 64-bit floating point values into 8-byte strings.
 */
final class DoublePacker
{
    /**
     * @param float $double
     * @return string
     */
    public static function pack($double)
    {
        $string = pack("d", $double);
        assert(8 === strlen($string));
        return $string;
    }
    
    /**
     * @param string $string
     * @return float
     * @throws StorageException When a conversion error occurs.
     */
    public static function unpack($string)
    {
        if (strlen($string) !== 8) {
            throw new StorageException("The string is not 64 bit long.");
        }
        $unpack = unpack("d", $string);
        if (!is_array($unpack) || !array_key_exists(1, $unpack)) {
            throw new StorageException("Could not unpack string.");
        }
        return $unpack[1];
    }
}
