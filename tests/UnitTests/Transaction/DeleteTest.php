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

class DeleteTest extends TestCase
{
    /**
     * @var TransactionConfig
     */
    private static $config;

    /**
     * @var Collection
     */
    private static $testCollection;

    /**
     * @var ObjectId
     */
    private $objectId;


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

        // use mongo directly to build fixture
        $insertResult = self::$testCollection->insertOne([
            'username' => 'A',
            'email'    => 'a@example.com',
            'name'     => 'AA',
        ]);

        $this->objectId = $insertResult->getInsertedId();
    }

    protected function tearDown()
    {
        self::$config->getStageChangeLogCollection()->drop();
        self::$config->getTransactionLogCollection()->drop();
        parent::tearDown();
    }

    public function testDeleteOneOnly()
    {
        $transaction = Transaction::begin(self::$config);

        $deleteResult = $transaction->deleteOne(self::$testCollection, [
            '_id' => $this->objectId
        ]);

        $this->assertEquals(1, $deleteResult->getDeletedCount());
        $this->assertEquals(0, self::$testCollection->count());
    }


    /**
     * @depends testDeleteOneOnly
     */
    public function testDeleteOneThenCommit()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_INIT, $transactionDocument['state']);

        $transaction->deleteOne(self::$testCollection, ['_id' => $this->objectId]);

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
     * @depends testDeleteOneThenCommit
     */
    public function testDeleteOneThenRollback()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionLogCollection()
            ->findOne(['_id' => $transaction->getTransactionId()]);

        $this->assertEquals(TransactionLog::STATE_INIT, $transactionDocument['state']);

        $transaction->deleteOne(self::$testCollection, ['_id' => $this->objectId]);

        $this->assertEquals(0, self::$testCollection->count());

        try {
            $transaction->rollback();
        } catch (CannotRollbackException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertEquals(1, self::$testCollection->count());

        $object = self::$testCollection->findOne(['_id' => $this->objectId]);
        $this->assertEquals('A', $object['username']);
        $this->assertEquals('a@example.com', $object['email']);
        $this->assertEquals('AA', $object['name']);

        $this->expectException(CannotCommitException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->commit();
    }
}