<?php


namespace PHP_Mongo_Transaction;


use MongoDB\BSON\ObjectId;
use MongoDB\Client;

class StateChangeLogRepository
{
    /**
     * @var ObjectId
     */
    private $transactionId;

    /**
     * StateChangeLogRepository constructor.
     *
     * @param ObjectId $transactionId
     */
    public function __construct(ObjectId $transactionId)
    {
        $this->transactionId = $transactionId;
    }


    public function save(StateChangeLog $log)
    {
        // TODO: use $push
        $collection = (new Client)->test->php_mongo_transaction_state_change_log;

        $doc = $collection->findOne(
            ['transaction_id' => $this->transactionId]
        );

        if (empty($doc)) {
            $collection->insertOne(
                [
                    'transaction_id' => $this->transactionId,
                    'logs'           => [],
                ]
            );
        }

        $result = (new Client)->test->php_mongo_transaction_state_change_log->updateOne([
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
    }

    /**
     * @return StateChangeLog[]
     */
    public function readAll()
    {
        $arr = (new Client)->test->php_mongo_transaction_state_change_log->findOne([
            'transaction_id' => $this->transactionId,
        ])['logs'];

        $logs = [];
        foreach ($arr as $item) {
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