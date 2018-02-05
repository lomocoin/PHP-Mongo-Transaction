<?php


namespace PHP_Mongo_Transaction;


use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;

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

        // TODO: should use MongoDB\BSON\Persistable
        $result = $collection->updateOne([
            'transaction_id' => $this->transactionId,
        ], [
            '$push' =>
                [
                    'logs' => [
                        'database_name'   => $log->getDatabaseName(),
                        'collection_name' => $log->getCollectionName(),
                        'type'            => $log->getType(),
                        'state_before'    => $log->getStateBefore(),
                        'state_after'     => $log->getStateAfter(),
                    ],
                ],
        ]);

        return $result;
    }

    /**
     * // TODO: potential memory issue, should use $pull or $each
     * @return StateChangeLog[]
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
            // TODO: should use MongoDB\BSON\Persistable
            $log = new StateChangeLog(
                $item['database_name'],
                $item['collection_name'],
                $item['type']);
            $log->setStateBefore($item['state_before']);
            $log->setStateAfter($item['state_after']);
            $logs[] = $log;
        }

        return $logs;
    }
}