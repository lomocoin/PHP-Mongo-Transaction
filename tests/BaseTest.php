<?php

namespace Lomocoin\Mongodb\Tests;

use MongoDB\Client;

class BaseTest extends TestCase
{
    public function testConnection()
    {
        $client = new Client($this->getMongoUri());

        $this->assertEquals($this->getMongoUri(), (string)$client);
    }

    public function testInsert()
    {
        $client     = new Client($this->getMongoUri());
        $collection = $client->selectCollection($this->getMongoDatabase(), $this->getMongoTransactionCollection());

        $collection->drop();

        $collection->insertOne([
            'username' => 'A',
            'email'    => 'a@example.com',
            'name'     => 'AA',
        ]);

        $collection->insertOne([
            'username' => 'B',
            'email'    => 'b@example.com',
            'name'     => 'BB',
        ]);

        $result = $collection->find();

        $this->assertInstanceOf(\MongoDB\Driver\Cursor::class, $result);
        $this->assertCount(2, $result->toArray());
    }
}