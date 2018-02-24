# PHP-Mongo-Transaction

## Overview

// TODO:

## Features

// TODO:

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