<?php

namespace RedisQueue\Tests;

use PHPUnit\Framework\TestCase;
use RedisQueue\Worker;
use RedisQueue\Message;

class WorkerTest extends TestCase
{
    /** @test */
    public function worker_can_be_extended()
    {
        $worker = new class extends Worker {
            public $lastMessage;

            public function do(Message $message)
            {
                $this->lastMessage = $message;
            }
        };

        $msg = new Message(['cmd' => 'test']);
        $worker->do($msg);

        $this->assertSame($msg, $worker->lastMessage);
    }

    /** @test */
    public function worker_works_with_client_loop()
    {
        $worker = new class extends Worker {
            public $called = false;

            public function do(Message $message)
            {
                $this->called = true;
            }
        };

        $msg = new Message(['cmd' => 'test']);
        $worker->do($msg);

        $this->assertTrue($worker->called);
    }
}
