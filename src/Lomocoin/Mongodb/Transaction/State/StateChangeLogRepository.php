<?php

namespace Lomocoin\Mongodb\Transaction\State;

use Lomocoin\Mongodb\Config\TransactionConfig;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

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
                    'logs'           => [],
                ]
            );
        }

        $result = $collection->updateOne([
            'transaction_id' => $this->transactionId,
        ], [
            '$push' => [
                'logs' => $log,
            ],
        ]);

        return $result;
    }

    /**
     * @todo Replace this method with generator for better control and memory issue
     * @return StateChangeLog[]
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function readAll()
    {
        /* @var $arr BSONArray */
        $arr = $this->config
                    ->getStageChangeLogCollection()
                    ->findOne([
                        'transaction_id' => $this->transactionId,
                    ])['logs'];
        $logs = $arr->getArrayCopy();
        return $logs;
    }
}