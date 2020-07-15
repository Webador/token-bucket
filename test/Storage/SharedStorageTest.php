<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\FileStorage;
use JouwWeb\TokenBucket\Storage\IPCStorage;
use JouwWeb\TokenBucket\Storage\LaminasStorage;
use JouwWeb\TokenBucket\Storage\MemcachedStorage;
use JouwWeb\TokenBucket\Storage\PDOStorage;
use JouwWeb\TokenBucket\Storage\PHPRedisStorage;
use JouwWeb\TokenBucket\Storage\PredisStorage;
use JouwWeb\TokenBucket\Storage\SessionStorage;
use JouwWeb\TokenBucket\Storage\Storage;
use JouwWeb\TokenBucket\Storage\StorageException;
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
class SharedStorageTest extends TestCase
{
    /** @var Storage[] Tests storages. */
    private $storages = [];
    
    protected function tearDown()
    {
        foreach ($this->storages as $storage) {
            try {
                @$storage->remove();
            } catch (StorageException $e) {
                // ignore missing vfsStream files.
            }
        }
    }
    
    /**
     * Tests two storages with different names don't interfere each other.
     *
     * @param callable $factory The storage factory.
     *
     * @dataProvider storageFactoryProvider
     */
    public function testStoragesDontInterfere(string $storageName, ?callable $factory)
    {
        if (!$factory) {
            $this->markTestSkipped(sprintf('%s factory is not available.', $storageName));
        }

        /** @var Storage $storageA */
        $storageA = $factory("A");
        $storageA->bootstrap(0);
        $storageA->getMicrotime();
        $this->storages[] = $storageA;

        /** @var Storage $storageB */
        $storageB = $factory("B");
        $storageB->bootstrap(0);
        $storageB->getMicrotime();
        $this->storages[] = $storageB;
        
        $storageA->setMicrotime(1);
        $storageB->setMicrotime(2);
        
        $this->assertNotEquals($storageA->getMicrotime(), $storageB->getMicrotime());
    }

    /**
     * @return mixed[][]
     */
    public function storageFactoryProvider(): array
    {
        $factories = [
            [
                'SessionStorage',
                function($name) {
                    return new SessionStorage($name);
                },
            ],
            [
                'FileStorage',
                function($name) {
                    vfsStream::setup('fileStorage');
                    return new FileStorage(vfsStream::url("fileStorage/$name"));
                },
            ],
            [
                'LaminasStorage',
                function($name) {
                    return new LaminasStorage($name, new Memory());
                },
            ]
        ];

        if (extension_loaded('sysvsem')) {
            $factories[] = [
                'IPCStorage',
                function($name) {
                    $key = ftok(__FILE__, $name);
                    return new IPCStorage($key);
                },
            ];
        } else {
            $factories[] = ['IPCStorage', null];
        }

        if (extension_loaded('pdo_sqlite')) {
            $factories[] = [
                'sqlite',
                function($name) {
                    $pdo = new \PDO("sqlite::memory:");
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new PDOStorage($name, $pdo);
                },
            ];
        } else {
            $factories[] = ['sqlite', null];
        }

        if (extension_loaded('pdo_mysql') && getenv("MYSQL_DSN")) {
            $factories[] = [
                'mysql',
                function($name) {
                    $pdo = new \PDO(getenv("MYSQL_DSN"), getenv("MYSQL_USER"));
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);

                    $storage = new PDOStorage($name, $pdo);

                    $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);

                    return $storage;
                },
            ];
        } else {
            $factories[] = ['mysql', null];
        }

        if (extension_loaded('pdo_pgsql') && getenv("PGSQL_DSN")) {
            $factories[] = [
                'pgsql',
                function($name) {
                    $pdo = new \PDO(getenv("PGSQL_DSN"), getenv("PGSQL_USER"));
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return new PDOStorage($name, $pdo);
                },
            ];
        } else {
            $factories[] = ['mysql', null];
        }

        if (extension_loaded('memcache') && getenv("MEMCACHE_HOST")) {
            $factories[] = [
                'memcache',
                function($name) {
                    $memcached = new \Memcached();
                    $memcached->addServer(getenv("MEMCACHE_HOST"), 11211);
                    return new MemcachedStorage($name, $memcached);
                },
            ];
        } else {
            $factories[] = ['memcache', null];
        }

        if (extension_loaded('redis') && getenv("REDIS_URI")) {
            $factories[] = [
                'redis',
                function($name) {
                    $uri   = parse_url(getenv("REDIS_URI"));
                    $redis = new \Redis();
                    $redis->connect($uri["host"]);
                    return new PHPRedisStorage($name, $redis);
                },
            ];
        } else {
            $factories[] = ['redis', null];
        }

        if (getenv("REDIS_URI")) {
            $factories[] = [
                'predis',
                function($name) {
                    $redis = new Client(getenv("REDIS_URI"));
                    return new PredisStorage($name, $redis);
                },
            ];
        } else {
            $factories[] = ['predis', null];
        }

        return $factories;
    }
}
