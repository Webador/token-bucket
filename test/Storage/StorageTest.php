<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Rate;
use JouwWeb\TokenBucket\Storage\FileStorage;
use JouwWeb\TokenBucket\Storage\IPCStorage;
use JouwWeb\TokenBucket\Storage\MemcachedStorage;
use JouwWeb\TokenBucket\Storage\PDOStorage;
use JouwWeb\TokenBucket\Storage\PHPRedisStorage;
use JouwWeb\TokenBucket\Storage\PredisStorage;
use JouwWeb\TokenBucket\Storage\SessionStorage;
use JouwWeb\TokenBucket\Storage\SingleProcessStorage;
use JouwWeb\TokenBucket\Storage\Storage;
use JouwWeb\TokenBucket\Storage\LaminasStorage;
use JouwWeb\TokenBucket\TokenBucket;
use Laminas\Cache\Storage\Adapter\Memory;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * If you want to run vendor specific tests you should provide these
 * environment variables:
 *
 * - MYSQL_DSN, MYSQL_USER
 * - PGSQL_DSN, PGSQL_USER
 * - MEMCACHE_HOST
 * - REDIS_URI
 */
class StorageTest extends TestCase
{
    /** @var Storage The tested storage. */
    private $storage;
    
    protected function tearDown()
    {
        if ($this->storage && $this->storage->isBootstrapped()) {
            $this->storage->remove();
        }
    }
    
    /**
     * Tests setMicrotime() and getMicrotime().
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testSetAndGetMicrotime(string $factoryName, ?callable $storageFactory): void
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $this->storage->bootstrap(1);
        $this->storage->getMicrotime();
        
        $this->storage->setMicrotime(1.1);
        $this->assertSame(1.1, $this->storage->getMicrotime());
        $this->assertSame(1.1, $this->storage->getMicrotime());
        
        $this->storage->setMicrotime(1.2);
        $this->assertSame(1.2, $this->storage->getMicrotime());
        
        $this->storage->setMicrotime(1436551945.0192);
        $this->assertSame(1436551945.0192, $this->storage->getMicrotime());
    }
    
    /**
     * Tests isBootstrapped().
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testBootstrap(string $factoryName, ?callable $storageFactory): void
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();

        $this->storage->bootstrap(123);
        $this->assertTrue($this->storage->isBootstrapped());
        $this->assertEquals(123, $this->storage->getMicrotime());
    }
    
    /**
     * Tests isBootstrapped().
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testIsBootstrapped(string $factoryName, ?callable $storageFactory)
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $this->assertFalse($this->storage->isBootstrapped());

        $this->storage->bootstrap(123);
        $this->assertTrue($this->storage->isBootstrapped());

        $this->storage->remove();
        $this->assertFalse($this->storage->isBootstrapped());
    }
    
    /**
     * Tests remove().
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testRemove(string $factoryName, ?callable $storageFactory)
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $this->storage->bootstrap(123);

        $this->storage->remove();
        $this->assertFalse($this->storage->isBootstrapped());
    }
    
    /**
     * When no tokens are available, the bucket should return false.
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testConsumingUnavailableTokensReturnsFalse(string $factoryName, ?callable $storageFactory)
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $capacity = 10;
        $rate = new Rate(1, Rate::SECOND);
        $bucket = new TokenBucket($capacity, $rate, $this->storage);
        $bucket->bootstrap(0);

        $this->assertFalse($bucket->consume(10));
    }
    
    /**
     * When tokens are available, the bucket should return true.
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testConsumingAvailableTokensReturnsTrue(string $factoryName, ?callable $storageFactory)
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $capacity = 10;
        $rate = new Rate(1, Rate::SECOND);
        $bucket = new TokenBucket($capacity, $rate, $this->storage);
        $bucket->bootstrap(10);

        $this->assertTrue($bucket->consume(10));
    }
    
    /**
     * Tests synchronized bootstrap
     *
     * @param callable $storageFactory Returns a storage.
     * @dataProvider storageFactoryProvider
     */
    public function testSynchronizedBootstrap(string $factoryName, ?callable $storageFactory)
    {
        if (!$storageFactory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $factoryName));
        }

        $this->storage = $storageFactory();
        $this->storage->getMutex()->synchronized(function () {
            $this->assertFalse($this->storage->isBootstrapped());
            $this->storage->bootstrap(123);
            $this->assertTrue($this->storage->isBootstrapped());
        });
    }

    /**
     * Provides uninitialized Storage implementations.
     *
     * @return mixed[][]
     */
    public function storageFactoryProvider(): array
    {
        $factories = [
            [
                "SingleProcessStorage",
                function() {
                    return new SingleProcessStorage();
                },
            ],
            [
                "SessionStorage",
                function() {
                    return new SessionStorage("test");
                },
            ],
            [
                "FileStorage",
                function() {
                    vfsStream::setup('fileStorage');
                    return new FileStorage(vfsStream::url("fileStorage/data"));
                },
            ],
            [
                "LaminasStorage",
                function() {
                    return new LaminasStorage('zend', new Memory());
                },
            ]
        ];

        if (extension_loaded('pdo_sqlite')) {
            $factories[] = [
                "sqlite",
                function () {
                    $pdo = new \PDO("sqlite::memory:");
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new PDOStorage("test", $pdo);
                },
            ];
        } else {
            $factories[] = ["sqlite", null];
        }

        if (extension_loaded('sysvsem')) {
            $factories[] = [
                "IPCStorage",
                function() {
                    return new IPCStorage(ftok(__FILE__, "a"));
                },
            ];
        } else {
            $factories[] = ['IPCStorage', null];
        }

        if (extension_loaded('pdo_mysql') && getenv("MYSQL_DSN")) {
            $factories[] = [
                "MYSQL",
                function() {
                    $pdo = new \PDO(getenv("MYSQL_DSN"), getenv("MYSQL_USER"));
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
                    return new PDOStorage("test", $pdo);
                },
            ];
        } else {
            $factories[] = ['MYSQL', null];
        }

        if (extension_loaded("pdo_pgsql") && getenv("PGSQL_DSN")) {
            $factories[] = [
                "PGSQL",
                function() {
                    $pdo = new \PDO(getenv("PGSQL_DSN"), getenv("PGSQL_USER"));
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new PDOStorage("test", $pdo);
                },
            ];
        } else {
            $factories[] = ['PGSQL', null];
        }

        if (extension_loaded('memcache') && getenv("MEMCACHE_HOST")) {
            $factories[] = [
                "MemcachedStorage",
                function() {
                    $memcached = new \Memcached();
                    $memcached->addServer(getenv("MEMCACHE_HOST"), 11211);
                    return new MemcachedStorage("test", $memcached);
                },
            ];
        } else {
            $factories[] = ['MemcachedStorage', null];
        }

        if (extension_loaded('redis') && getenv("REDIS_URI")) {
            $factories[] = [
                "PHPRedisStorage",
                function() {
                    $uri = parse_url(getenv("REDIS_URI"));
                    $redis = new \Redis();
                    $redis->connect($uri["host"]);
                    return new PHPRedisStorage("test", $redis);
                },
            ];
        } else {
            $factories[] = ["PHPRedisStorage", null];
        }

        if (getenv("REDIS_URI")) {
            $factories[] = [
                "PredisStorage",
                function() {
                    $redis = new Client(getenv("REDIS_URI"));
                    return new PredisStorage("test", $redis);
                },
            ];
        } else {
            $factories[] = ['PredisStorage', null];
        }

        return $factories;
    }
}
