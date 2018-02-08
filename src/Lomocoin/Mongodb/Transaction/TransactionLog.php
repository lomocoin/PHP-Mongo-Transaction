<?php


namespace Lomocoin\Mongodb\Transaction;


use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Persistable;

class TransactionLog implements Persistable, \ArrayAccess
{
    const STATE_INIT     = 'init';
    const STATE_ONGOING  = 'ongoing';
    const STATE_COMMIT   = 'commit';
    const STATE_ROLLBACK = 'rollback';

    /**
     * @var ObjectId
     */
    private $id;

    /**
     * @var string
     */
    private $state;

    /**
     * @var int
     */
    private $createdAt;

    /**
     * @var int
     */
    private $updatedAt;

    /**
     * TransactionLog constructor.
     *
     * @param string $state
     */
    public function __construct(string $state = self::STATE_INIT)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $this->id        = new ObjectId;
        $this->state     = $state;
        $this->createdAt = time();
        $this->updatedAt = time();
    }

    /**
     * @return ObjectId
     */
    public function getId(): ObjectId
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return int
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * @return int
     */
    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
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
            '_id'        => $this->id,
            'state'      => $this->state,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
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
        $this->id        = $data['_id'];
        $this->state     = $data['state'];
        $this->createdAt = $data['created_at'];
        $this->updatedAt = $data['updated_at'];
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        $valid = [
            '_id',
            'state',
            'created_at',
            'updated_at',
        ];

        return \in_array($offset, $valid, true);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     *
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        switch ($offset) {
            case '_id':
                return $this->getId();
            case 'state':
                return $this->getState();
            case 'created_at':
                return $this->getCreatedAt();
            case 'updated_at':
                return $this->getUpdatedAt();
            default:
                return null;
        }
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     *
     * @return void
     * @since 5.0.0
     * @throws \UnderflowException
     */
    public function offsetSet($offset, $value)
    {
        throw new \UnderflowException('You can not do that');
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     *
     * @return void
     * @since 5.0.0
     * @throws \UnderflowException
     */
    public function offsetUnset($offset)
    {
        throw new \UnderflowException('You can not do that');
    }
}