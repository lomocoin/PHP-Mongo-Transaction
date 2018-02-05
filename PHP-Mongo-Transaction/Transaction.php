<?php

namespace PHP_Mongo_Transaction;

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

// TODO: should add a timestamp to allow clean(no longer needed) and check(errors?) transaction logs by cron
class Transaction
{
    /**
     * @var ObjectId
     */
    private $objectId;

    /**
     * @var TransactionConfig
     */
    private $config;

    /**
     * @var StateChangeLog
     */
    private $logRepo;

    const STATE_INIT     = 'init';
    const STATE_ONGOING  = 'ongoing';
    const STATE_COMMIT   = 'commit';
    const STATE_ROLLBACK = 'rollback';

    /**
     * @return mixed
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    private function __construct(ObjectId $uuid, TransactionConfig $config)
    {
        $this->objectId = $uuid;
        $this->config   = $config;
        $this->logRepo  = new StateChangeLogRepository($uuid, $config);
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
        $this->updateStateAfterTransactionBegin();

        // log the after state
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_INSERT_ONE);

        $objectId   = $insertResult->getInsertedId();
        $stateAfter = $collection->findOne(['_id' => $objectId]);
        /** @var BSONDocument $stateAfter */
        $log->setStateAfter($stateAfter);
        $this->logRepo->save($log);

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

        // execute update operation
        $updateResult = $collection->updateOne($filter, $update, $options);
        $this->updateStateAfterTransactionBegin();

        // log the after state
        $stateAfter = $collection->findOne($filter);
        /** @var BSONDocument $stateAfter */
        $log->setStateAfter($stateAfter);
        $this->logRepo->save($log);

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
        $this->logRepo->save($log);

        // execute delete operation
        $deleteResult = $collection->deleteOne($filter);
        $this->updateStateAfterTransactionBegin();

        return $deleteResult;
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
        $insertOneResult = $config
            ->getTransactionCollection()
            ->insertOne([
                'state' => self::STATE_INIT,
            ]);

        $id = $insertOneResult->getInsertedId();

        return new self($id, $config);
    }

    /**
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function commit()
    {
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $this->objectId,
            ], [
                '$set' => [
                    'state' => self::STATE_COMMIT,
                ],
            ]);
    }

    /**
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws CannotRollbackException
     */
    public function rollback()
    {
        // check the state
        $transactionDocument = $this->config->getTransactionCollection()->findOne(['_id' => $this->objectId]);
        if ($transactionDocument['state'] !== self::STATE_ONGOING) {
            throw new CannotRollbackException('The transaction state is not valid');
        }

        // read all logs, reverse the order, then apply rollback 1 by 1
        $logs = array_reverse($this->logRepo->readAll());
        foreach ($logs as $log) {
            $this->rollbackOne($log);
        }

        // mark the state of transaction
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $this->objectId,
            ], [
                '$set' => [
                    'state' => self::STATE_ROLLBACK,
                ],
            ]);
    }

    /**
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    private function updateStateAfterTransactionBegin()
    {
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $this->objectId,
            ], [
                '$set' => [
                    'state' => self::STATE_ONGOING,
                ],
            ]);
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