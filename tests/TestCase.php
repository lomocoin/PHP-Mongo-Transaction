<?php

namespace Lomocoin\Mongodb\Tests;

use Lomocoin\Mongodb\Config\TransactionConfig;
use MongoDB\Client;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * get mongo uri
     *
     * @return string
     */
    public static function getMongoUri()
    {
        return getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
    }

    /**
     * get mongo database
     *
     * @return string
     */
    public static function getMongoDatabase()
    {
        return getenv('MONGODB_DATABASE') ?: 'lomocoin_mongodb_test';
    }

    /**
     * get mongo collection
     *
     * @return string
     */
    public static function getMongoTransactionCollection()
    {
        return getenv('MONGODB_COLLECTION') ?: 'lomocoin_mongodb_test_transaction_log';
    }

    /**
     * get mongo transaction state log collection
     *
     * @return string
     */
    public static function getMongoTransactionStateLogCollection()
    {
        return getenv('MONGODB_TRANSACTION_LOG_COLLECTION') ?: 'lomocoin_mongodb_test_transaction_state_change_log';
    }

    /**
     * get basic config for common testing case
     *
     * @return TransactionConfig
     */
    public static function getBasicConfig()
    {
        return new TransactionConfig(
            new Client(self::getMongoUri()),
            self::getMongoDatabase(),
            self::getMongoTransactionCollection(),
            self::getMongoTransactionStateLogCollection());
    }
}