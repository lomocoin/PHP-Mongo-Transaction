<?php

namespace PHP_Mongo_Transaction;

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
    private $transactionCollectionName;

    /**
     * @var string
     */
    private $stageChangeLogCollectionName;

    /**
     * TransactionConfig constructor.
     *
     * @param Client $mongoDBClient
     * @param string $databaseName
     * @param string $transactionCollectionName
     * @param string $stageChangeLogCollectionName
     */
    public function __construct(
        Client $mongoDBClient,
        string $databaseName,
        string $transactionCollectionName,
        string $stageChangeLogCollectionName
    ) {
        $this->mongoDBClient                = $mongoDBClient;
        $this->databaseName                 = $databaseName;
        $this->transactionCollectionName    = $transactionCollectionName;
        $this->stageChangeLogCollectionName = $stageChangeLogCollectionName;
    }

    /**
     * @return \MongoDB\Collection
     */
    public function getTransactionCollection() {
        $client = $this->getMongoDBClient();
        $databaseName = $this->getDatabaseName();
        $collectionName = $this->getTransactionCollectionName();
        return $client->$databaseName->$collectionName;
    }

    /**
     * @return \MongoDB\Collection
     */
    public function getStageChangeLogCollection() {
        $client = $this->getMongoDBClient();
        $databaseName = $this->getDatabaseName();
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
    public function getTransactionCollectionName(): string
    {
        return $this->transactionCollectionName;
    }

    /**
     * @return string
     */
    public function getStageChangeLogCollectionName(): string
    {
        return $this->stageChangeLogCollectionName;
    }
}