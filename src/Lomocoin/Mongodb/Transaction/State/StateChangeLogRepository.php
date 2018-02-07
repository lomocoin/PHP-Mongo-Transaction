<?php

namespace Lomocoin\Mongodb\Transaction;

use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;
use Lomocoin\Mongodb\Config\TransactionConfig;

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
     * readAll
     *
     * @return StateChangeLog[]
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function readAll()
    {
        $arr = $this->config->getStageChangeLogCollection()->findOne([
            'transaction_id' => $this->transactionId,
        ])['logs'];

        $logs = [];
        /** @var BSONDocument[] $arr */
        foreach ($arr as $item) {
            $log = new StateChangeLog(
                $item->getDatabaseName(),
                $item->getCollectionName(),
                $item->getType());

            $log->setStateBefore($item->getStateBefore());
            $log->setStateAfter($item->getStateAfter());
            $logs[] = $log;
        }

        return $logs;
    }
}