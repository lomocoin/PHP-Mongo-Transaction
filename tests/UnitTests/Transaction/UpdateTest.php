<?php


namespace Lomocoin\Mongodb\Tests\UnitTests\Transaction;


use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Exception\CannotCommitException;
use Lomocoin\Mongodb\Exception\CannotRollbackException;
use Lomocoin\Mongodb\Tests\TestCase;
use Lomocoin\Mongodb\Transaction\Transaction;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

class UpdateTest extends TestCase
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
        self::$config->getTransactionCollection()->drop();
        parent::tearDown();
    }

    public function testUpdateOneOnly()
    {
        // use transaction
        $transaction = Transaction::begin(self::$config);

        $updateResult = $transaction->updateOne(
            self::$testCollection,
            [
                '_id' => $this->objectId,
            ],
            [
                '$set' => [
                    'name' => 'BB',
                ],
            ]);

        $this->assertEquals(1, $updateResult->getMatchedCount());
        $this->assertEquals(1, $updateResult->getModifiedCount());

        $object = self::$testCollection->findOne(['_id' => $this->objectId]);
        $this->assertEquals('A', $object['username']);
        $this->assertEquals('a@example.com', $object['email']);
        $this->assertEquals('BB', $object['name']);
    }

    /**
     * @depends testUpdateOneOnly
     */
    public function testUpdateOneThenCommit()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionCollection()
            ->findOne(['_id' => $transaction->getObjectId()]);

        $this->assertEquals(Transaction::STATE_INIT, $transactionDocument['state']);

        $transaction->updateOne(
            self::$testCollection,
            [
                '_id' => $this->objectId,
            ],
            [
                '$set' => [
                    'name' => 'BB',
                ],
            ]);

        $transactionDocument = self::$config
            ->getTransactionCollection()
            ->findOne(['_id' => $transaction->getObjectId()]);

        $this->assertEquals(Transaction::STATE_ONGOING, $transactionDocument['state']);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->commit();

        $transactionDocument = self::$config
            ->getTransactionCollection()
            ->findOne(['_id' => $transaction->getObjectId()]);

        $this->assertEquals(Transaction::STATE_COMMIT, $transactionDocument['state']);

        $this->expectException(CannotRollbackException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->rollback();
    }

    /**
     * @depends testUpdateOneThenCommit
     */
    public function testUpdateOneThenRollback()
    {
        $transaction = Transaction::begin(self::$config);

        $transactionDocument = self::$config
            ->getTransactionCollection()
            ->findOne(['_id' => $transaction->getObjectId()]);

        $this->assertEquals(Transaction::STATE_INIT, $transactionDocument['state']);

        $transaction->updateOne(
            self::$testCollection,
            [
                '_id' => $this->objectId,
            ],
            [
                '$set' => [
                    'name' => 'BB',
                ],
            ]);

        $this->assertEquals(1, self::$testCollection->count());

        try {
            $transaction->rollback();
        } catch (CannotRollbackException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertEquals(1, self::$testCollection->count());

        $object = self::$testCollection->findOne(
            [
                '_id' => $this->objectId,
            ]
        );
        $this->assertEquals('AA', $object['name']);

        $this->expectException(CannotCommitException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->commit();
    }
}