<?php

namespace JouwWeb\TokenBucket\Test\Util;

use JouwWeb\TokenBucket\Util\DoublePacker;

class DoublePackerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param string $expected
     * @param float $input
     * @dataProvider provideTestPack
     */
    public function testPack($expected, $input)
    {
        $this->assertEquals($expected, DoublePacker::pack($input));
    }
    
    /**
     * Provides test cases for testPack().
     *
     * @return array Test cases.
     */
    public function provideTestPack()
    {
        return [
            [pack("d", 0)  , 0],
            [pack("d", 0.1), 0.1],
            [pack("d", 1)  , 1],
        ];
    }
    
    /**
     * @param string $input The input string.
     * @dataProvider provideTestUnpackFails
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testUnpackFails($input)
    {
        DoublePacker::unpack($input);
    }
    
    /**
     * Provides test cases for testUnpackFails().
     *
     * @return array Test cases.
     */
    public function provideTestUnpackFails()
    {
        return [
            [""],
            ["1234567"],
            ["123456789"],
        ];
    }

    /**
     * @param float $expected
     * @param string $input
     * @dataProvider provideTestUnpack
     */
    public function testUnpack($expected, $input)
    {
        $this->assertEquals($expected, DoublePacker::unpack($input));
    }
    
    /**
     * Provides test cases for testConvert().
     *
     * @return array Test cases.
     */
    public function provideTestUnpack()
    {
        return [
            [0,   pack("d", 0)],
            [0.1, pack("d", 0.1)],
            [1,   pack("d", 1)],
            [1.1, pack("d", 1.1)],
        ];
    }
}
