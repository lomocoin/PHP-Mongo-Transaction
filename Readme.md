## PHP-Mongo-Transaction

## Install

```bash
composer require ........
```

## Usage


#### 1、Require `autoload.php` file

```php
require __DIR__ . '/vendor/autoload.php';
```

#### 2、Create transaction object

```php
$config = new \PHP_Mongo_Transaction\TransactionConfig(
    new \MongoDB\Client(),
    'test',
    'php_mongo_transaction_transaction',
    'php_mongo_transaction_state_change_log');
    
$transaction = Transaction::begin($config);
```

#### 3、Rollback

###### 3.1 insert

```php
$transaction->insertOne($collection, [
    'username' => 'B',
    'email'    => 'b@example.com',
    'name'     => 'BB',
]);
```

###### 3.2 update

```php
$transaction->updateOne($collection, [
    'username' => 'B',
], [
    '$set' => [
        'name' => 'BBB',
    ],
]);
```

###### 3.3 rollback

```php
$transaction->rollback();
```

## Contributors

[Shenghan Chen](https://github.com/zzdjk6)
[viest](https://github.com/viest)

## License

Apache License 2.0