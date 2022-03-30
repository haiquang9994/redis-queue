# Install
```bash
composer require lpks/redis-queue
```

# Usage
## Worker
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use RedisQueue\Client;
use RedisQueue\Message;
use RedisQueue\Woker;

class Woker1 extends Woker
{
    public function do(Message $message)
    {
        if ($message->cmd === 'write') {
            $content = $message->text;
            echo "$content\n";
        }
    }
}

try {
    $client = new Client();
    $client->loop('test_queue', new Woker1());
} catch (Exception $e) {
    echo $e->getMessage();
}
```

## Queue
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