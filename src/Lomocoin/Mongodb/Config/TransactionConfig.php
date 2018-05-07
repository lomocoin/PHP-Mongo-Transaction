<?php

namespace Lomocoin\Mongodb\Config;

use MongoDB\Client;

class TransactionConfig
{
    /**
     * @var Client
     */
    private $mongoDBClient;

    /**
     * @var string
     */
    private $databaseName;

    /**
     * @var string
     */
    private $transactionLogCollectionName;

    /**
     * @var string
     */
    private $stageChangeLogCollectionName;

    /**
     * TransactionConfig constructor.
     *
     * @param Client $mongoDBClient
     * @param string $databaseName
     * @param string $transactionLogCollectionName
     * @param string $stageChangeLogCollectionName
     */
    public function __construct(
        Client $mongoDBClient,
        string $databaseName,
        string $transactionLogCollectionName,
        string $stageChangeLogCollectionName
    ) {
        $this->mongoDBClient                = $mongoDBClient;
        $this->databaseName                 = $databaseName;
        $this->transactionLogCollectionName = $transactionLogCollectionName;
        $this->stageChangeLogCollectionName = $stageChangeLogCollectionName;
    }

    /**
     * @return \MongoDB\Collection
     */
    public function getTransactionLogCollection()
    {
        $client         = $this->getMongoDBClient();
        $databaseName   = $this->getDatabaseName();
        $collectionName = $this->getTransactionLogCollectionName();

        return $client->$databaseName->$collectionName;
    }

    /**
     * @return \MongoDB\Collection
     */
    public function getStageChangeLogCollection()
    {
        $client         = $this->getMongoDBClient();
        $databaseName   = $this->getDatabaseName();
        $collectionName = $this->getStageChangeLogCollectionName();

        return $client->$databaseName->$collectionName;
    }

    /**
     * @return Client
     */
    public function getMongoDBClient(): Client
    {
        return $this->mongoDBClient;
    }

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    /**
     * @return string
     */
    public function getTransactionLogCollectionName(): string
    {
        return $this->transactionLogCollectionName;
    }

    /**
     * @return string
     */
    public function getStageChangeLogCollectionName(): string
    {
        return $this->stageChangeLogCollectionName;
    }

    /**
     * @return \MongoDB\Database
     */
    public function getMongoDatabase()
    {
        return $this->mongoDBClient->{$this->databaseName};
    }
}