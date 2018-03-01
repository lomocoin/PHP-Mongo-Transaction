# PHP-Mongo-Transaction

## Overview

Do you miss transaction in RDBMS while using MongoDB? You are not alone.

A news said that MongoDB will support ACID in the future, but what if we need it now?

Well, `PHP-Mongo-Transaction` provides a simply basic transaction feature similar to RDBMS.

The flow is simple, just begin a transaction, do something, then commit or rollback.

The concept to achieve this is also simple: build a record collection in MongoDB to trace the modification of the data, and recover when rollback.

That is, once a transaction wants to rollback, it will:

* delete what has been inserted
* insert back what has been deleted (with the same ID)
* replace the modified data with the original copy (with the same ID) 

To achieve this, this lib wraps the basic `insertOne`, `updateOne`, `deleteOne` functions which provided by MongoDB Driver.

Have a look at `#Usage` part of this document to see how easy to use it.

## Limitation

1. We assume the database just works, so if there is a database failure, the transaction may not be rollback correctly and will be leaving as `ongoing` state. You may need to have a cron job to detect if everything works fine, and investigate manually when something goes wrong. If everything goes smoothly, the state of transactions should be either `commit` or `rollback`, except the real `init` and `ongoing` ones.

2. We can't handle concurrency issues at the current stage. It's too complicated to ensure data consist under the concurrent scenario. We recommend that you consider using a simple lock mechanism to avoid two transactions that may write to the same record happen at the same time, or adapting a message queue system to maintain the order of transaction execution.

## Roadmap

* More unit tests
* Enhance docs
* Support `insertMany`, `updateMany`, `deleteMany`

## Notice

You can use `Persistable` class with this library, but do not do any magic in the `bsonSerialize` and `bsonUnserialize`, e.g: auto update the "last modified date".

**Any magic in these two functions will definitely destroy the valid data state**

## Install

```bash
composer require PHP-Mongodb-Transaction
```

## Usage


### 1. Require `autoload.php` file

```php
require __DIR__ . '/vendor/autoload.php';
```

### 2. Create transaction object

```php
$config = new \PHP_Mongo_Transaction\TransactionConfig(
    new \MongoDB\Client(),
    'test',
    'transaction_log',
    'state_change_log');
    
$transaction = Transaction::begin($config);
```

### 3. Make the change

#### 3.1 insert

```php
$transaction->insertOne($collection, [
    'username' => 'B',
    'email'    => 'b@example.com',
    'name'     => 'BB',
]);
```

#### 3.2 update

```php
$transaction->updateOne($collection, [
    'username' => 'B',
], [
    '$set' => [
        'name' => 'BBB',
    ],
]);
```

#### 3.3 delete

```php
$transaction->deleteOne(collection, ['username' => 'B']);
```

### 4. Commit or Rollback

```php
// if no error happens, you commit
$transaction->commit();

// if any error happens, you rollback
$transaction->rollback();
```

## Contributors

[Shenghan Chen](https://github.com/zzdjk6) | 
[viest](https://github.com/viest)

## License

Apache License 2.0