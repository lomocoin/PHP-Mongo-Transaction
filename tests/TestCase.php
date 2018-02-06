<?php

namespace Lomocoin\Mongodb\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * get mongo uri
     *
     * @return string
     */
    public function getMongoUri()
    {
        return getenv('MONGODB_URI') ? : 'mongodb://127.0.0.1:27017';
    }

    /**
     * get mongo database
     *
     * @return string
     */
    public function getMongoDatabase()
    {
        return getenv('MONGODB_DATABASE') ? : 'lomocoin_mongodb_test';
    }

    /**
     * get mongo collection
     *
     * @return string
     */
    public function getMongoTransactionCollection()
    {
        return getenv('MONGODB_COLLECTION') ? : 'lomocoin_mongodb_test_transaction';
    }

    /**
     * get mongo transaction state log collection
     *
     * @return string
     */
    public function getMongoTransactionStateLogCollection()
    {
        return getenv('MONGODB_TRANSACTION_LOG_COLLECTION') ? : 'lomocoin_mongodb_test_transaction_state_change_log';
    }
}