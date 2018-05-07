<?php

namespace Lomocoin\Mongodb\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use Lomocoin\Mongodb\Exception\CannotCommitException;
use Lomocoin\Mongodb\Exception\CannotRollbackException;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

class Transaction
{
    /**
     * @var ObjectId
     */
    private $transactionId;

    /**
     * @var TransactionConfig
     */
    private $config;

    /**
     * @var StateChangeLogRepository
     */
    private $stateChangeLogRepo;

    /**
     * @var TransactionLogRepository
     */
    private $transactionLogRepo;

    /* @var $_instance self */
    private static $_instance = null;

    private function __construct(ObjectId $uuid, TransactionConfig $config)
    {
        $this->transactionId      = $uuid;
        $this->config             = $config;
        $this->stateChangeLogRepo = new StateChangeLogRepository($uuid, $config);
        $this->transactionLogRepo = new TransactionLogRepository($config);
    }

    /**
     * @param TransactionConfig $config
     *
     * @return Transaction
     * @throws \MongoDB\Exception\BadMethodCallException
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public static function begin(TransactionConfig $config)
    {
        $transactionLogRepo = new TransactionLogRepository($config);
        $transactionLog     = $transactionLogRepo->create();
        $id                 = $transactionLog->getId();

        if (self::$_instance === null) {
            self::$_instance = new self($id, $config);
        }

        return self::$_instance;
    }

    /**
     * @return ObjectId
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     *
     * @param Collection $collection
     * @param array|object $document
     * @param array $options
     *
     * @return \MongoDB\InsertOneResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function insertOne(Collection $collection, $document, array $options = [])
    {
        // execute insert operation
        $insertResult = $collection->insertOne($document, $options);
        $this->transactionLogRepo->markOngoing($this->transactionId);

        // log the after state
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_INSERT_ONE);

        $objectId   = $insertResult->getInsertedId();
        $stateAfter = $collection->findOne(['_id' => $objectId]);
        /** @var BSONDocument $stateAfter */
        $log->setStateAfter($stateAfter);
        $this->stateChangeLogRepo->save($log);

        return $insertResult;
    }

    /**
     *
     * @param Collection $collection
     * @param array|object $filter
     * @param array|object $update
     * @param array $options
     *
     * @return \MongoDB\UpdateResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function updateOne(Collection $collection, $filter, $update, array $options = [])
    {
        // log the before state
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_UPDATE_ONE);

        $stateBefore = $collection->findOne($filter);
        /** @var BSONDocument $stateBefore */
        $log->setStateBefore($stateBefore);

        // use _id to avoid issues when deploy mongo sharding
        $filter = ['_id' => $stateBefore['_id']];

        // execute update operation
        $updateResult = $collection->updateOne($filter, $update, $options);
        $this->transactionLogRepo->markOngoing($this->transactionId);

        // log the after state
        $stateAfter = $collection->findOne($filter);
        /** @var BSONDocument $stateAfter */
        $log->setStateAfter($stateAfter);
        $this->stateChangeLogRepo->save($log);

        return $updateResult;
    }

    /**
     * @param Collection $collection
     * @param array|object $filter
     * @param array $options
     *
     * @return \MongoDB\DeleteResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function deleteOne(Collection $collection, $filter, array $options = [])
    {
        // log the before state
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_DELETE_ONE);

        $stateBefore = $collection->findOne($filter);
        /** @var BSONDocument $stateBefore */
        $log->setStateBefore($stateBefore);
        $this->stateChangeLogRepo->save($log);

        // use _id to avoid issues when deploy mongo sharding
        $filter = ['_id' => $stateBefore['_id']];

        // execute delete operation
        $deleteResult = $collection->deleteOne($filter, $options);
        $this->transactionLogRepo->markOngoing($this->transactionId);

        return $deleteResult;
    }

    /**
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws CannotCommitException
     */
    public function commit()
    {
        // check the state
        if ($this->transactionLogRepo->canCommit($this->transactionId) === false) {
            throw new CannotCommitException('The transaction state is not valid');
        }

        $this->transactionLogRepo->markCommit($this->transactionId);
    }

    /**
     * @throws \MongoDB\Exception\UnexpectedValueException
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws CannotRollbackException
     */
    public function rollback()
    {
        if ($this->transactionLogRepo->canRollback($this->transactionId) === false) {
            throw new CannotRollbackException('The transaction state is not valid');
        }

        // rollback log 1 by 1
        while ($log = $this->stateChangeLogRepo->read()) {
            $this->rollbackOne($log);
        }

        $this->transactionLogRepo->markRollback($this->transactionId);
    }

    /**
     * @param StateChangeLog $log
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    private function rollbackOne(StateChangeLog $log)
    {
        $databaseName   = $log->getDatabaseName();
        $collectionName = $log->getCollectionName();

        // we assume that the operation on actual data use the same connection with the config
        $collection = $this->config->getMongoDBClient()->$databaseName->$collectionName;

        switch ($log->getType()) {
            case StateChangeLog::TYPE_INSERT_ONE:
                $collection->deleteOne([
                    '_id' => $log->getStateAfter()['_id'],
                ]);
                break;
            case StateChangeLog::TYPE_UPDATE_ONE:
                $collection->replaceOne([
                    '_id' => $log->getStateAfter()['_id'],
                ], $log->getStateBefore());
                break;
            case StateChangeLog::TYPE_DELETE_ONE:
                $collection->insertOne($log->getStateBefore());
                break;
        }
    }
}