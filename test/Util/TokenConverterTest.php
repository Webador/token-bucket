<?php

namespace JouwWeb\TokenBucket\Test\Util;

use JouwWeb\TokenBucket\Rate;
use JouwWeb\TokenBucket\Util\TokenConverter;
use PHPUnit\Framework\TestCase;

class TokenConverterTest extends TestCase
{
    /**
     * Tests convertSecondsToTokens().
     *
     * @param int $expected
     * @param float $seconds
     * @param Rate $rate
     * @dataProvider provideTestConvertSecondsToTokens
     */
    public function testConvertSecondsToTokens($expected, $seconds, Rate $rate)
    {
        $converter = new TokenConverter($rate);
        $this->assertEquals($expected, $converter->convertSecondsToTokens($seconds));
    }
    
    /**
     * Provides test cases for testConvertSecondsToTokens().
     *
     * @return array Test cases.
     */
    public function provideTestConvertSecondsToTokens()
    {
        return [
            [0, 0.9, new Rate(1, Rate::SECOND)],
            [1, 1,   new Rate(1, Rate::SECOND)],
            [1, 1.1, new Rate(1, Rate::SECOND)],

            [1000, 1, new Rate(1, Rate::MILLISECOND)],
            [2000, 2, new Rate(1, Rate::MILLISECOND)],

            [0, 59, new Rate(1, Rate::MINUTE)],
            [1, 60, new Rate(1, Rate::MINUTE)],
            [1, 61, new Rate(1, Rate::MINUTE)],
        ];
    }
    
    /**
     * Tests convertTokensToSeconds().
     *
     * @param float $expected
     * @param int $tokens
     * @param Rate $rate
     * @dataProvider provideTestconvertTokensToSeconds
     */
    public function testconvertTokensToSeconds($expected, $tokens, Rate $rate)
    {
        $converter = new TokenConverter($rate);
        $this->assertEquals($expected, $converter->convertTokensToSeconds($tokens));
    }
    
    /**
     * Provides test cases for testconvertTokensToSeconds().
     *
     * @return array Test cases.
     */
    public function provideTestconvertTokensToSeconds()
    {
        return [
            [0.001, 1, new Rate(1, Rate::MILLISECOND)],
            [0.002, 2, new Rate(1, Rate::MILLISECOND)],
            [1, 1, new Rate(1, Rate::SECOND)],
            [2, 2, new Rate(1, Rate::SECOND)],
        ];
    }
    
    /**
     * Tests convertTokensToMicrotime().
     *
     * @param float $delta
     * @param int $tokens
     * @param Rate $rate
     * @dataProvider provideTestConvertTokensToMicrotime
     */
    public function testConvertTokensToMicrotime($delta, $tokens, Rate $rate)
    {
        $converter = new TokenConverter($rate);

        $this->assertEquals(microtime(true) + $delta, $converter->convertTokensToMicrotime($tokens), '', 0.001);
    }
    
    /**
     * Provides test cases for testConvertTokensToMicrotime().
     *
     * @return array Test cases.
     */
    public function provideTestConvertTokensToMicrotime()
    {
        return [
            [-1, 1, new Rate(1, Rate::SECOND)],
            [-2, 2, new Rate(1, Rate::SECOND)],
            [-0.001, 1, new Rate(1, Rate::MILLISECOND)],
        ];
    }
}
