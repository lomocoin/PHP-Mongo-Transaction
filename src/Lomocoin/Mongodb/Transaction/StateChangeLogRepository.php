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

        $result = $collection->updateOne([
            'transaction_id' => $this->transactionId,
        ], [
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
            $collection->updateOne(
                ['transaction_id' => $this->transactionId],
                [
                    '$push' => ['rollback_logs' => $log],
                ]);

            $collection->updateOne(
                ['transaction_id' => $this->transactionId],
                [
                    '$pop' => ['operation_logs' => 1],
                ]);
        }

        return $log;
    }
}