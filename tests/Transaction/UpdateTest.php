<?php

namespace Lomocoin\Mongodb\Tests\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Transaction\Transaction;
use Lomocoin\Mongodb\Tests\TestCase;
use MongoDB\Client;

class UpdateTest extends TestCase
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

    public function testUpdate()
    {
        $this->transaction->updateOne($this->collection, [
            'username' => 'B',
        ], [
            '$set' => [
                'name' => 'BBB',
            ],
        ]);

        $this->transaction->rollback();

        $findResult = $this->collection->findOne(['username' => 'B']);

        $this->assertEquals('BB', $findResult['name']);
    }
}