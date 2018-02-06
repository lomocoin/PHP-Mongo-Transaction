<?php

namespace Lomocoin\Mongodb\Tests\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Transaction\Transaction;
use Lomocoin\Mongodb\Tests\TestCase;
use MongoDB\Client;

class DeleteTest extends TestCase
{
    private $collection;
    private $transaction;

    public function setUp()
    {
        parent::setUp();

        $client = (new Client($this->getMongoUri()));

        $this->collection = $client->selectDatabase($this->getMongoDatabase())
            ->selectCollection($this->getMongoTransactionCollection());

        $config = new TransactionConfig(
            $client,
            $this->getMongoDatabase(),
            $this->getMongoTransactionCollection(),
            $this->getMongoTransactionStateLogCollection());

        $this->transaction = Transaction::begin($config);
    }

    public function testDelete()
    {
        $this->transaction->deleteOne($this->collection, [
            'username' => 'B',
        ]);

        $this->transaction->rollback();

        $findResult = $this->collection->findOne(['username' => 'B']);

        $this->assertInstanceOf(\MongoDB\Model\BSONDocument::class,$findResult);
    }
}