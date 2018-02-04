<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHP_Mongo_Transaction\Transaction;

// TODO: phpunit

$collection = (new MongoDB\Client)->test->users;
$collection->drop();

$collection->insertOne([
    'username' => 'A',
    'email'    => 'a@example.com',
    'name'     => 'AA',
]);

$collection->insertOne([
    'username' => 'B',
    'email'    => 'b@example.com',
    'name'     => 'BB',
]);

function printLine($str)
{
    echo date('Y-m-d H:i:s') . ' | ' . $str, "\n";
}

printLine('Raw state');
print_r($collection->find()->toArray());

$transaction = Transaction::begin();

// -- insert
$transaction->insertOne($collection, [
    'username' => 'C',
    'email'    => 'c@example.com',
    'name'     => 'CC',
]);

printLine('After insert state');
print_r($collection->find()->toArray());

// --- update
$transaction->updateOne($collection, [
    'username' => 'B',
], [
    '$set' => [
        'name' => 'BBB',
    ],
]);
printLine('After update state');
print_r($collection->find()->toArray());

// -- delete
$transaction->deleteOne($collection, [
    'username' => 'B',
]);
printLine('After delete state');
print_r($collection->find()->toArray());

$transaction->rollback();
printLine('Rollback state');
print_r($collection->find()->toArray());