<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use RedisQueue\Client;
use RedisQueue\Message;
use RedisQueue\Worker;

class WorkerSample extends Worker
{
    public function do(Message $message)
    {
        if ($message->cmd === 'write') {
            $content = $message->text;
            echo "$content\n";
        }
    }
}

$client = new Client();
$client->enableGracefulShutdown();

try {
    $client->loop('test_queue', new WorkerSample());
} catch (Exception $e) {
    echo '[Server] Fatal error: ' . $e->getMessage() . "\n";
    exit(1);
}
