<?php

namespace Lomocoin\Mongodb\Transaction;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Persistable;
use MongoDB\Model\BSONDocument;

class StateChangeLog implements Persistable
{
    const TYPE_INSERT_ONE = 'insert_one';
    const TYPE_UPDATE_ONE = 'update_one';
    const TYPE_DELETE_ONE = 'delete_one';

    /**
     * @var ObjectId
     */
    private $id;

    /**
     * @var string
     */
    private $databaseName;

    /**
     * @var string
     */
    private $collectionName;

    /**
     * @var string
     */
    private $type;

    /**
     * @var BSONDocument|null
     */
    private $stateBefore;

    /**
     * @var BSONDocument|null
     */
    private $stateAfter;

    /**
     * StateChangeLog constructor.
     *
     * @param string $databaseName
     * @param string $collectionName
     * @param string $type
     */
    public function __construct(string $databaseName, string $collectionName, string $type)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $this->id             = new ObjectId;
        $this->databaseName   = $databaseName;
        $this->collectionName = $collectionName;
        $this->type           = $type;
    }

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    /**
     * @return string
     */
    public function getCollectionName(): string
    {
        return $this->collectionName;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @return BSONDocument|null
     */
    public function getStateAfter()
    {
        return $this->stateAfter;
    }

    /**
     * @return BSONDocument|null
     */
    public function getStateBefore()
    {
        return $this->stateBefore;
    }

    /**
     * @param BSONDocument|null $stateAfter
     */
    public function setStateAfter($stateAfter)
    {
        if ($this->type === self::TYPE_DELETE_ONE) {
            return;
        }

        $this->stateAfter = $stateAfter;
    }

    /**
     * @param BSONDocument|null $stateBefore
     */
    public function setStateBefore($stateBefore)
    {
        if ($this->type === self::TYPE_INSERT_ONE) {
            return;
        }

        $this->stateBefore = $stateBefore;
    }

    /**
     * Provides an array or document to serialize as BSON
     * Called during serialization of the object to BSON. The method must return an array or stdClass.
     * Root documents (e.g. a MongoDB\BSON\Serializable passed to MongoDB\BSON\fromPHP()) will always be serialized as a BSON document.
     * For field values, associative arrays and stdClass instances will be serialized as a BSON document and sequential arrays (i.e. sequential, numeric indexes starting at 0) will be serialized as a BSON array.
     * @link http://php.net/manual/en/mongodb-bson-serializable.bsonserialize.php
     * @return array|object An array or stdClass to be serialized as a BSON array or document.
     */
    public function bsonSerialize()
    {
        return [
            '_id'             => $this->id,
            'database_name'   => $this->databaseName,
            'collection_name' => $this->collectionName,
            'type'            => $this->type,
            'state_before'    => $this->stateBefore,
            'state_after'     => $this->stateAfter,
        ];
    }

    /**
     * Constructs the object from a BSON array or document
     * Called during unserialization of the object from BSON.
     * The properties of the BSON array or document will be passed to the method as an array.
     * @link http://php.net/manual/en/mongodb-bson-unserializable.bsonunserialize.php
     *
     * @param array $data Properties within the BSON array or document.
     */
    public function bsonUnserialize(array $data)
    {
        $this->id             = $data['_id'];
        $this->databaseName   = $data['database_name'];
        $this->collectionName = $data['collection_name'];
        $this->type           = $data['type'];
        $this->stateBefore    = $data['state_before'];
        $this->stateAfter     = $data['state_after'];
    }
}