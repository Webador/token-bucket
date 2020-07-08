<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\FileStorage;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;

class FileStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests opening the file fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testOpeningFails()
    {
        vfsStream::setup('test');
        @new FileStorage(vfsStream::url("test/nonexisting/test"));
    }

    /**
     * Tests readinging too little in getMicrotime().
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testGetMicrotimeReadsToLittle()
    {
        $data = new vfsStreamFile("data");
        $data->setContent("1234567");
        vfsStream::setup('test')->addChild($data);
        
        $storage = new FileStorage(vfsStream::url("test/data"));
        $storage->getMicrotime();
    }

    /**
     * Tests deleting fails.
     *
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testRemoveFails()
    {
        $data = new vfsStreamFile("data");
        $root = vfsStream::setup('test');
        $root->chmod(0);
        $root->addChild($data);
        
        $storage = new FileStorage(vfsStream::url("test/data"));
        $storage->remove();
    }
}
