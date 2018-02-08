<?php

namespace Lomocoin\Mongodb\Tests\UnitTests\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Tests\TestCase;
use Lomocoin\Mongodb\Transaction\Transaction;
use MongoDB\Collection;

class RollbackTest extends TestCase
{
    /**
     * @var TransactionConfig
     */
    private static $config;

    /**
     * @var Collection
     */
    private static $testCollection;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$config = self::getBasicConfig();

        // share same database name with transaction log
        $databaseName         = self::$config->getDatabaseName();
        self::$testCollection = self::$config->getMongoDBClient()->$databaseName->unit_test;
    }

    protected function setUp()
    {
        parent::setUp();
        self::$testCollection->drop();
    }

    protected function tearDown()
    {
        self::$config->getStageChangeLogCollection()->drop();
        self::$config->getTransactionCollection()->drop();
        parent::tearDown();
    }

    public function testRollbackAll()
    {
        // baisc fixture
        $objectIdA = self::$testCollection
            ->insertOne([
                'username' => 'A',
                'email'    => 'a@example.com',
                'name'     => 'AA',
            ])
            ->getInsertedId();

        // test begin
        $transaction = Transaction::begin(self::$config);

        $objectIdB = $transaction
            ->insertOne(self::$testCollection, [
                'username' => 'B',
                'email'    => 'b@example.com',
                'name'     => 'BB',
            ])
            ->getInsertedId();

        $objectIdC = $transaction
            ->insertOne(self::$testCollection, [
                'username' => 'C',
                'email'    => 'c@example.com',
                'name'     => 'CC',
            ])
            ->getInsertedId();

        $transaction->updateOne(
            self::$testCollection,
            [
                'username' => 'B',
            ],
            [
                '$set' => [
                    'name' => 'BBB',
                ],
            ]);

        $transaction->deleteOne(self::$testCollection, ['username' => 'C']);

        $this->assertEquals(2, self::$testCollection->count());

        $objectA = self::$testCollection->findOne(['_id' => $objectIdA]);
        $this->assertEquals('A', $objectA['username']);
        $this->assertEquals('a@example.com', $objectA['email']);
        $this->assertEquals('AA', $objectA['name']);

        $objectB = self::$testCollection->findOne(['_id' => $objectIdB]);
        $this->assertEquals('B', $objectB['username']);
        $this->assertEquals('b@example.com', $objectB['email']);
        $this->assertEquals('BBB', $objectB['name']);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->rollback();

        $this->assertEquals(1, self::$testCollection->count());

        $objectA = self::$testCollection->findOne(['_id' => $objectIdA]);
        $this->assertEquals('A', $objectA['username']);
        $this->assertEquals('a@example.com', $objectA['email']);
        $this->assertEquals('AA', $objectA['name']);
    }
}