<?php

namespace Lomocoin\Mongodb\Tests\UnitTests\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Exception\CannotCommitException;
use Lomocoin\Mongodb\Exception\CannotRollbackException;
use Lomocoin\Mongodb\Tests\TestCase;
use Lomocoin\Mongodb\Transaction\Transaction;
use Lomocoin\Mongodb\Transaction\TransactionLog;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

class InsertTest extends TestCase
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
        self::$config->getTransactionLogCollection()->drop();
        parent::tearDown();
    }

    public function testInsertOneOnly()
    {
        $transaction = Transaction::begin(self::$config);

        $insertResult = $transaction->insertOne(self::$testCollection, [
            'username' => 'A',
            'email'    => 'a@example.com',
            'name'     => 'AA',
        ]);

        $this->assertEquals(1, $insertResult->getInsertedCount());

        $objectId = $insertResult->getInsertedId();
        $this->assertInstanceOf(ObjectId::class, $objectId);

        $object = self::$testCollection->findOne(['_id' => $objectId]);
        $this->assertEquals('A', $object['username']);
        $this->assertEquals('a@example.com', $object['email']);
        $this->assertEquals('AA', $object['name']);
    }

    /**
     * @depends testInsertOneOnly
     */
    public function testInsertOneThenCommit()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_INIT, $transactionDocument['state']);

        $transaction->insertOne(self::$testCollection, [
            'username' => 'A',
            'email'    => 'a@example.com',
            'name'     => 'AA',
        ]);

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_ONGOING, $transactionDocument['state']);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->commit();

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_COMMIT, $transactionDocument['state']);

        $this->expectException(CannotRollbackException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->rollback();
    }

    /**
     * @depends testInsertOneThenCommit
     */
    public function testInsertOneThenRollback()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_INIT, $transactionDocument['state']);

        $transaction->insertOne(self::$testCollection, [
            'username' => 'A',
            'email'    => 'a@example.com',
            'name'     => 'AA',
        ]);

        $this->assertEquals(1, self::$testCollection->count());

        try {
            $transaction->rollback();
        } catch (CannotRollbackException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertEquals(0, self::$testCollection->count());

        $this->expectException(CannotCommitException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->commit();
    }
}