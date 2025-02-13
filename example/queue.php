<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use RedisQueue\Client;

try {
    $client = new Client();

    $data = [
        'cmd' => 'write',
        'text' => 'Hello world!',
    ];

    $client->push('test_queue', $data);

    echo "RPUSH " . json_encode($data) . " .\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
