<?php

namespace RedisQueue\Tests;

use PHPUnit\Framework\TestCase;
use RedisQueue\Client;
use RedisQueue\Message;
use RedisQueue\Worker;
use Exception;

class ClientTest extends TestCase
{
    private $mockRedis;

    protected function createClientWithMock($host = '127.0.0.1', $port = 6379, array $options = []): Client
    {
        $this->mockRedis = $this->getMockBuilder(\Predis\Client::class)
            ->onlyMethods(['__call'])
            ->getMock();

        return new class($host, $port, $options, $this->mockRedis) extends Client {
            private $mock;

            public function __construct($host, $port, $options, $mock)
            {
                $this->mock = $mock;
                parent::__construct($host, $port, $options);
            }

            protected function connectRedis()
            {
                $this->predisClient = $this->mock;
            }
        };
    }

    /** @test */
    public function constructor_does_not_connect_redis_immediately()
    {
        $client = new Client('1.2.3.4', 9999);
        $this->assertInstanceOf(Client::class, $client);
    }

    /** @test */
    public function constructor_accepts_options()
    {
        $client = new Client('127.0.0.1', 6379, [
            'password' => 'secret',
            'database' => 1,
        ]);
        $this->assertInstanceOf(Client::class, $client);
    }

    /** @test */
    public function push_calls_rpush_and_returns_this()
    {
        $client = $this->createClientWithMock();

        $this->mockRedis->expects($this->once())
            ->method('__call')
            ->with('rpush', $this->callback(function ($args) {
                return $args[0] === 'test_queue' && strpos($args[1], 'hello') !== false;
            }));

        $result = $client->push('test_queue', ['text' => 'hello']);
        $this->assertSame($client, $result);
    }

    /** @test */
    public function push_retries_on_redis_error()
    {
        $client = $this->createClientWithMock();

        $this->mockRedis->expects($this->exactly(3))
            ->method('__call')
            ->with('rpush')
            ->willThrowException(new Exception('Connection refused'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Connection refused');

        $client->push('test_queue', ['text' => 'x'], 3);
    }

    /** @test */
    public function push_retry_succeeds_on_second_attempt()
    {
        $client = $this->createClientWithMock();

        $this->mockRedis->expects($this->exactly(2))
            ->method('__call')
            ->with('rpush')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new Exception('Timeout')),
                $this->returnValue(1)
            );

        $result = $client->push('test_queue', ['text' => 'ok'], 3);
        $this->assertSame($client, $result);
    }

    /** @test */
    public function stop_sets_shouldStop_flag()
    {
        $client = new Client();
        $client->stop();

        $reflection = new \ReflectionProperty(Client::class, 'shouldStop');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->getValue($client));
    }

    /** @test */
    public function setLogger_logger_is_invoked_on_error()
    {
        $logged = [];

        $logger = function ($msg) use (&$logged) {
            $logged[] = $msg;
        };

        $this->mockRedis = $this->getMockBuilder(\Predis\Client::class)
            ->onlyMethods(['__call'])
            ->getMock();

        $this->mockRedis->method('__call')
            ->with('rpush')
            ->willThrowException(new Exception('fail'));

        $client = new class($this->mockRedis, $logger) extends Client {
            public function __construct($mock, $logger)
            {
                $this->predisClient = $mock;
                $this->logger = $logger;
                parent::__construct();
            }

            protected function connectRedis()
            {
                // already connected
            }
        };

        try {
            $client->push('q', ['a' => 1], 1);
        } catch (Exception $e) {
            // expected
        }

        $this->assertNotEmpty($logged);
        $this->assertStringContainsString('Push failed', $logged[0]);
    }

    /** @test */
    public function loop_calls_worker_when_message_received()
    {
        $client = $this->createClientWithMock();
        $worker = $this->createMock(Worker::class);

        $this->mockRedis->method('__call')
            ->with('brpop')
            ->willReturnCallback(function () use ($client) {
                $client->stop();
                return ['test_queue', '{"cmd":"write","text":"hi"}'];
            });

        $worker->expects($this->once())
            ->method('do')
            ->with($this->callback(function (Message $msg) {
                return $msg->cmd === 'write' && $msg->text === 'hi';
            }));

        $client->loop('test_queue', $worker);
    }

    /** @test */
    public function loop_skips_invalid_json()
    {
        $client = $this->createClientWithMock();
        $worker = $this->createMock(Worker::class);

        $this->mockRedis->method('__call')
            ->with('brpop')
            ->willReturnCallback(function () use ($client) {
                $client->stop();
                return ['test_queue', 'invalid json{'];
            });

        $worker->expects($this->never())->method('do');

        $client->loop('test_queue', $worker);
    }

    /** @test */
    public function loop_worker_exception_does_not_crash()
    {
        $client = $this->createClientWithMock();
        $worker = $this->createMock(Worker::class);

        $this->mockRedis->method('__call')
            ->with('brpop')
            ->willReturnCallback(function () use ($client) {
                $client->stop();
                return ['test_queue', '{"cmd":"write"}'];
            });

        $worker->method('do')
            ->willThrowException(new Exception('Worker crashed'));

        $client->loop('test_queue', $worker);
        $this->assertTrue(true);
    }

    /** @test */
    public function loop_reconnects_on_redis_error()
    {
        $client = $this->createClientWithMock();
        $worker = $this->createMock(Worker::class);

        $callCount = 0;
        $this->mockRedis->method('__call')
            ->with('brpop')
            ->willReturnCallback(function () use ($client, &$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new Exception('Lost connection');
                }
                $client->stop();
                return ['test_queue', '{"cmd":"ok"}'];
            });

        $worker->expects($this->once())->method('do');

        $client->loop('test_queue', $worker);
    }

    /** @test */
    public function enableGracefulShutdown_registers_signal_handlers()
    {
        if (!function_exists('pcntl_signal')) {
            $this->markTestSkipped('ext-pcntl not available');
        }

        $client = new Client();
        $client->enableGracefulShutdown();

        $this->assertTrue(true);
    }

    /** @test */
    public function setLogger_returns_this()
    {
        $client = new Client();
        $result = $client->setLogger(function ($msg) {});
        $this->assertSame($client, $result);
    }
}
