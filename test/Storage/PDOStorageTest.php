<?php

namespace JouwWeb\TokenBucket\Test\Storage;

use JouwWeb\TokenBucket\Storage\PDOStorage;
use JouwWeb\TokenBucket\Storage\Storage;
use PHPUnit\Framework\TestCase;

/**
 * If you want to run vendor specific PDO tests you should provide these
 * environment variables:
 *
 * - MYSQL_DSN, MYSQL_USER
 * - PGSQL_DSN, PGSQL_USER
 */
class PDOStorageTest extends TestCase
{
    /** @var Storage[] The tested storages. */
    private $storages = [];

    protected function tearDown()
    {
        foreach ($this->storages as $storage) {
            $storage->remove();
        }
    }

    /**
     * Tests instantiation with a too long name should fail.
     *
     * @expectedException \LengthException
     */
    public function testTooLongNameFails()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('"pdo_sqlite" extension is not loaded.');
        }

        $pdo = new \PDO("sqlite::memory:");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        new PDOStorage(str_repeat(" ", 129), $pdo);
    }

    /**
     * Tests instantiation with a long name should not fail.
     */
    public function testLongName()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('"pdo_sqlite" extension is not loaded.');
        }

        $pdo = new \PDO("sqlite::memory:");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        new PDOStorage(str_repeat(" ", 128), $pdo);
    }

    /**
     * Tests instantiation with PDO in wrong error mode should fail.
     *
     * @param int $errorMode The invalid error mode.
     * @expectedException \InvalidArgumentException
     * @dataProvider invalidErrorModeProvider
     */
    public function testInvalidErrorMode(int $errorMode)
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('"pdo_sqlite" extension is not loaded.');
        }

        $pdo = new \PDO("sqlite::memory:");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, $errorMode);
        new PDOStorage("test", $pdo);
    }

    /**
     * Provides test cases for testInvalidErrorMode()
     *
     * @return int[][] Invalid error modes.
     */
    public function invalidErrorModeProvider()
    {
        return [
            [\PDO::ERRMODE_SILENT],
            [\PDO::ERRMODE_WARNING],
        ];
    }

    /**
     * Tests instantiation with PDO in valid error mode.
     */
    public function testValidErrorMode()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('"pdo_sqlite" extension is not loaded.');
        }

        $pdo = new \PDO("sqlite::memory:");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        new PDOStorage("test", $pdo);
    }

    /**
     * Tests bootstrap() adds a row to an existing table.
     *
     * @param \PDO $pdo The PDO.
     * @dataProvider pdoProvider
     */
    public function testBootstrapAddsRow(string $pdoName, ?\PDO $pdo)
    {
        if (!$pdo) {
            $this->markTestSkipped(sprintf('%s PDO is not available.', $pdoName));
        }

        $storageA = new PDOStorage("A", $pdo);
        $storageA->bootstrap(1);
        $this->storages[] = $storageA;

        $storageB = new PDOStorage("B", $pdo);
        $storageB->bootstrap(2);
        $this->storages[] = $storageB;
        
        $this->assertEquals(1, $storageA->getMicrotime());
        $this->assertEquals(2, $storageB->getMicrotime());
    }

    /**
     * Tests bootstrap() would add a row to an existing table, but fails.
     *
     * @param \PDO $pdo The PDO.
     * @dataProvider pdoProvider
     * @expectedException \JouwWeb\TokenBucket\Storage\StorageException
     */
    public function testBootstrapFailsForExistingRow(string $pdoName, ?\PDO $pdo)
    {
        if (!$pdo) {
            $this->markTestSkipped(sprintf('%s PDO is not available.', $pdoName));
        }

        $storageA = new PDOStorage("A", $pdo);
        $storageA->bootstrap(0);
        $this->storages[] = $storageA;

        $storageA2 = new PDOStorage("A", $pdo);
        $storageA2->bootstrap(0);
    }

    /**
     * Tests remove() removes only one row.
     *
     * @param \PDO $pdo The PDO.
     * @dataProvider pdoProvider
     */
    public function testRemoveOneRow(string $pdoName, ?\PDO $pdo)
    {
        if (!$pdo) {
            $this->markTestSkipped(sprintf('%s PDO is not available.', $pdoName));
        }

        $storageA = new PDOStorage("A", $pdo);
        $storageA->bootstrap(0);
        $this->storages[] = $storageA;

        $storageB = new PDOStorage("B", $pdo);
        $storageB->bootstrap(0);
        $storageB->remove();

        $this->assertTrue($storageA->isBootstrapped());
        $this->assertFalse($storageB->isBootstrapped());
    }

    /**
     * Tests remove() removes the table after the last row.
     *
     * @param \PDO $pdo The PDO.
     * @dataProvider pdoProvider
     */
    public function testRemoveTable(string $pdoName, ?\PDO $pdo)
    {
        if (!$pdo) {
            $this->markTestSkipped(sprintf('%s PDO is not available.', $pdoName));
        }

        $storage = new PDOStorage("test", $pdo);
        $storage->bootstrap(0);
        $storage->remove();
        $this->assertFalse($storage->isBootstrapped());
    }

    /**
     * @return mixed[][]
     */
    public function pdoProvider(): array
    {
        $pdos = [];

        if (extension_loaded('pdo_sqlite')) {
            $pdo = new \PDO("sqlite::memory:");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdos[] = ['sqlite', $pdo];
        } else {
            $pdos[] = ['sqlite', null];
        }

        if (extension_loaded('pdo_mysql') && getenv("MYSQL_DSN")) {
            $pdo = new \PDO(getenv("MYSQL_DSN"), getenv("MYSQL_USER"));
            $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, false);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdos[] = ['mysql', $pdo];
        } else {
            $pdos[] = ['mysql', null];
        }

        if (extension_loaded('pdo_pgsql') && getenv("PGSQL_DSN")) {
            $pdo = new \PDO(getenv("PGSQL_DSN"), getenv("PGSQL_USER"));
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdos[] = ['pgsql', $pdo];
        } else {
            $pdos[] = ['pgsql', null];
        }

        return $pdos;
    }
}
