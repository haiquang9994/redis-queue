# Install
```bash
composer require lpks/redis-queue
```

# Usage

## Worker (Consumer)

Xử lý message từ queue:

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
$client->enableGracefulShutdown(); // Cho phép Ctrl+C / kill

try {
    $client->loop('test_queue', new WorkerSample());
} catch (Exception $e) {
    echo '[Server] Fatal error: ' . $e->getMessage() . "\n";
    exit(1);
}
```

## Queue (Producer)

Đẩy message vào queue:

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
- `$options` — Các tùy chọn Redis bổ sung (ví dụ: `password`, `database`, `prefix`, `scheme`)

### Methods

| Method | Mô tả |
|--------|-------|
| `push(string $name, array $data, int $retries = 3)` | Đẩy message vào queue, tự động retry nếu Redis lỗi |
| `loop(string $name, Worker $worker)` | Vòng lặp xử lý message, tự động reconnect nếu mất kết nối |
| `stop()` | Dừng vòng lặp xử lý |
| `enableGracefulShutdown()` | Bắt SIGTERM/SIGINT để tắt server an toàn (yêu cầu `ext-pcntl`) |
| `setLogger(callable $logger)` | Inject custom logger (vd: `function($msg) { echo $msg; }`) |

### Ví dụ nâng cao: Redis có password, custom logger
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

| Method | Mô tả |
|--------|-------|
| `$message->key` | Magic getter, lấy giá trị theo key |
| `$message->get(string $key, $default = null)` | Lấy giá trị với giá trị mặc định |
| `$message->has(string $key): bool` | Kiểm tra key có tồn tại không |
| `$message->toArray(): array` | Lấy toàn bộ dữ liệu dạng array |
| `isset($message->key)` | Kiểm tra key tồn tại |

```php
$text = $message->get('text', 'default value');
if ($message->has('cmd')) { ... }
$allData = $message->toArray();
```