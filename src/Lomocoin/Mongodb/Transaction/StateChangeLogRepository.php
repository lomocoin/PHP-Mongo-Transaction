<?php

namespace Lomocoin\Mongodb\Transaction;

use Lomocoin\Mongodb\Config\TransactionConfig;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Cursor;

class StateChangeLogRepository
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
     * StateChangeLogRepository constructor.
     *
     * @param ObjectId $transactionId
     * @param TransactionConfig $config
     */
    public function __construct(ObjectId $transactionId, TransactionConfig $config)
    {
        $this->transactionId = $transactionId;
        $this->config        = $config;
    }

    /**
     * @param StateChangeLog $log
     *
     * @return \MongoDB\UpdateResult
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function save(StateChangeLog $log)
    {
        $collection = $this->config->getStageChangeLogCollection();

        $document = $collection->findOne(
            ['transaction_id' => $this->transactionId]
        );

        if (empty($document)) {
            $collection->insertOne(
                [
                    'transaction_id' => $this->transactionId,
                    'operation_logs' => [],
                    'rollback_logs'  => [],
                ]
            );
        }

        // use _id to avoid issues when deploy mongo sharding
        $logRepo = $collection->findOne(['transaction_id' => $this->transactionId], ['projection' => ['_id' => 1]]);
        $filter  = ['_id' => $logRepo['_id']];

        $result = $collection->updateOne($filter,
            [
                '$push' => [
                    'operation_logs' => $log,
                ],
            ]);

        return $result;
    }

    /**
     * @return StateChangeLog|null
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\UnexpectedValueException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function read()
    {
        $collection = $this->config->getStageChangeLogCollection();

        /* @var $cursor Cursor */
        $cursor = $collection->aggregate([
            ['$match' => ['transaction_id' => $this->transactionId]],
            [
                '$project' => [
                    'log' => [
                        '$arrayElemAt' => ['$operation_logs', -1],
                    ],
                ],
            ],
        ]);
        $arr    = $cursor->toArray();
        $data   = reset($arr);

        if (isset($data['log']) === false) {
            return null;
        }

        /* @var $log StateChangeLog */
        $log = $data['log'];

        if ($log) {
            // use _id to avoid issues when deploy mongo sharding
            $logRepo = $collection->findOne(['transaction_id' => $this->transactionId], ['projection' => ['_id' => 1]]);
            $filter  = ['_id' => $logRepo['_id']];

            $collection->updateOne(
                $filter,
                [
                    '$push' => ['rollback_logs' => $log],
                ]);

            $collection->updateOne(
                $filter,
                [
                    '$pop' => ['operation_logs' => 1],
                ]);
        }

        return $log;
    }
}