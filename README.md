# Install
```bash
composer require lpks/redis-queue
```

# Usage

## Worker (Consumer)

Process messages from the queue:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

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
$client->enableGracefulShutdown(); // Allow Ctrl+C / kill

try {
    $client->loop('test_queue', new WorkerSample());
} catch (Exception $e) {
    echo '[Server] Fatal error: ' . $e->getMessage() . "\n";
    exit(1);
}
```

## Queue (Producer)

Push messages into the queue:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

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
```

# API Reference

## `Client` class

### Constructor
```php
$client = new Client(string $host = '127.0.0.1', int $port = 6379, array $options = []);
```
- `$options` — Additional Redis options (e.g. `password`, `database`, `prefix`, `scheme`)

### Methods

| Method | Description |
|--------|-------------|
| `push(string $name, array $data, int $retries = 3)` | Push a message to the queue, auto-retry on Redis error |
| `loop(string $name, Worker $worker)` | Infinite loop processing messages, auto-reconnect on disconnection |
| `stop()` | Stop the processing loop |
| `enableGracefulShutdown()` | Catch SIGTERM/SIGINT to shut down safely (requires `ext-pcntl`) |
| `setLogger(callable $logger)` | Inject a custom logger (e.g. `function($msg) { echo $msg; }`) |

### Advanced example: Redis with password and custom logger
```php
$client = new Client('127.0.0.1', 6379, [
    'password' => 'secret',
    'database' => 1,
]);

$client->setLogger(function ($message) {
    echo date('[Y-m-d H:i:s] ') . $message . "\n";
});

$client->enableGracefulShutdown();
$client->loop('my_queue', new MyWorker());
```

## `Message` class

| Method | Description |
|--------|-------------|
| `$message->key` | Magic getter, returns value by key |
| `$message->get(string $key, $default = null)` | Get value with a default fallback |
| `$message->has(string $key): bool` | Check if a key exists |
| `$message->toArray(): array` | Get all data as an array |
| `isset($message->key)` | Check if a key exists |

```php
$text = $message->get('text', 'default value');
if ($message->has('cmd')) { ... }
$allData = $message->toArray();
```