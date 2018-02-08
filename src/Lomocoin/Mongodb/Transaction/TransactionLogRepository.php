<?php


namespace Lomocoin\Mongodb\Transaction;


use Lomocoin\Mongodb\Config\TransactionConfig;
use MongoDB\BSON\ObjectId;

class TransactionLogRepository
{
    /**
     * @var TransactionConfig
     */
    private $config;

    /**
     * TransactionLogRepository constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return TransactionLog
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function create()
    {
        $log = new TransactionLog();
        $this->config->getTransactionCollection()->insertOne($log);

        return $log;
    }

    /**
     * @param ObjectId $id
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function markOngoing(ObjectId $id)
    {
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $id,
            ], [
                '$set' => [
                    'state'      => TransactionLog::STATE_ONGOING,
                    'updated_at' => time(),
                ],
            ]);
    }

    /**
     * @param ObjectId $id
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function markCommit(ObjectId $id)
    {
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $id,
            ], [
                '$set' => [
                    'state'      => TransactionLog::STATE_COMMIT,
                    'updated_at' => time(),
                ],
            ]);
    }

    /**
     * @param ObjectId $id
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function markRollback(ObjectId $id)
    {
        $this->config
            ->getTransactionCollection()
            ->updateOne([
                '_id' => $id,
            ], [
                '$set' => [
                    'state'      => TransactionLog::STATE_ROLLBACK,
                    'updated_at' => time(),
                ],
            ]);
    }

    /**
     * @param ObjectId $id
     *
     * @return bool
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function canCommit(ObjectId $id)
    {
        $transactionDocument = $this->config->getTransactionCollection()->findOne(['_id' => $id]);

        return $transactionDocument['state'] === TransactionLog::STATE_INIT || $transactionDocument['state'] === TransactionLog::STATE_ONGOING;
    }

    /**
     * @param ObjectId $id
     *
     * @return bool
     *
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function canRollback(ObjectId $id)
    {
        $transactionDocument = $this->config->getTransactionCollection()->findOne(['_id' => $id]);

        return $transactionDocument['state'] === TransactionLog::STATE_INIT || $transactionDocument['state'] === TransactionLog::STATE_ONGOING;
    }
}