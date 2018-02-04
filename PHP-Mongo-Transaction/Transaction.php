<?php

namespace PHP_Mongo_Transaction;

use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;

// TODO: allow config the database collection
class Transaction
{
    /**
     * @var ObjectId
     */
    private $objectId;

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

    private function __construct($uuid)
    {
        $this->objectId = $uuid;
        $this->logRepo  = new StateChangeLogRepository($uuid);
    }

    /**
     * @param Collection $collection
     * @param array $document
     *
     * @return \MongoDB\InsertOneResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function insertOne(Collection $collection, array $document)
    {
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_INSERT);

        $insertResult = $collection->insertOne($document);
        $objectId     = $insertResult->getInsertedId();
        $stateAfter   = $collection->findOne(['_id' => $objectId]);
        $log->setStateAfter($stateAfter);
        $this->logRepo->save($log);

        return $insertResult;
    }

    /**
     * @param Collection $collection
     * @param array $filter
     * @param array $update
     *
     * @return \MongoDB\UpdateResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function updateOne(Collection $collection, array $filter, array $update)
    {
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_UPDATE);

        $stateBefore = $collection->findOne($filter);
        $log->setStateBefore($stateBefore);
        $updateResult = $collection->updateOne($filter, $update);
        $stateAfter   = $collection->findOne($filter);
        $log->setStateAfter($stateAfter);
        $this->logRepo->save($log);

        return $updateResult;
    }

    public function deleteOne(Collection $collection, array $filter)
    {
        $log = new StateChangeLog(
            $collection->getDatabaseName(),
            $collection->getCollectionName(),
            StateChangeLog::TYPE_DELETE);

        $stateBefore = $collection->findOne($filter);
        $log->setStateBefore($stateBefore);
        $deleteResult = $collection->deleteOne($filter);
        $this->logRepo->save($log);

        return $deleteResult;
    }

    /**
     * @return Transaction
     * @throws \MongoDB\Exception\BadMethodCallException
     * @throws \MongoDB\Driver\Exception\InvalidArgumentException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public static function begin()
    {
        $insertOneResult = (new Client)
            ->test->php_mongo_transaction_transaction
            ->insertOne([
                'state' => self::STATE_INIT,
            ]);

        $id = $insertOneResult->getInsertedId();

        return new self($id);
    }

    public function commit()
    {
        (new Client)
            ->test->php_mongo_transaction_transaction
            ->updateOne([
                '_id' => $this->objectId,
            ], [
                '$set' => [
                    'state' => self::STATE_COMMIT,
                ],
            ]);
    }

    public function rollback()
    {
        $logs = array_reverse($this->logRepo->readAll());
        foreach ($logs as $log) {
            $this->rollbackOne($log);
        }

        (new Client)
            ->test->php_mongo_transaction_transaction
            ->updateOne([
                '_id' => $this->objectId,
            ], [
                '$set' => [
                    'state' => self::STATE_ROLLBACK,
                ],
            ]);
    }

    private function rollbackOne(StateChangeLog $log)
    {
        $databaseName   = $log->getDatabaseName();
        $collectionName = $log->getCollectionName();
        $collection     = (new Client)->$databaseName->$collectionName;

        switch ($log->getType()) {
            case StateChangeLog::TYPE_INSERT:
                $collection->deleteOne([
                    '_id' => $log->getStateAfter()['_id'],
                ]);
                break;
            case StateChangeLog::TYPE_UPDATE:
                $collection->replaceOne([
                    '_id' => $log->getStateAfter()['_id'],
                ], $log->getStateBefore());
                break;
            case StateChangeLog::TYPE_DELETE:
                $collection->insertOne($log->getStateBefore());
                break;
        }
    }
}