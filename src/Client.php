<?php

namespace RedisQueue;

use Exception;
use Predis\Client as PredisClient;

class Client
{
    protected $host;
    protected $port;
    protected $options;
    protected $predisClient;
    protected $shouldStop = false;
    protected $logger;

    public function __construct($host = '127.0.0.1', $port = 6379, array $options = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->options = $options;
    }

    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function stop()
    {
        $this->shouldStop = true;
    }

    public function enableGracefulShutdown()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                echo "\n[RedisQueue] Received SIGTERM, shutting down gracefully...\n";
                $this->stop();
            });
            pcntl_signal(SIGINT, function () {
                echo "\n[RedisQueue] Received SIGINT, shutting down gracefully...\n";
                $this->stop();
            });
        }
    }

    protected function ensureConnected()
    {
        if (!$this->predisClient) {
            $this->connectRedis();
        }
    }

    protected function connectRedis()
    {
        $this->predisClient = new PredisClient(array_merge([
            'host'                => $this->host,
            'port'                => $this->port,
            'read_write_timeout'  => 0,
            'connection_timeout'  => 5,
        ], $this->options));
    }

    public function push(string $name, array $data, int $retries = 3)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $retries) {
            try {
                $this->ensureConnected();
                $this->predisClient->rpush($name, json_encode($data));
                return $this;
            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;
                $this->predisClient = null;
                $this->log("[RedisQueue] Push failed (attempt {$attempts}/{$retries}): " . $e->getMessage());

                if ($attempts < $retries) {
                    sleep($attempts);
                }
            }
        }

        throw $lastException;
    }

    public function loop(string $name, Worker $worker)
    {
        $retryDelay = 1;
        $this->ensureConnected();

        while (!$this->shouldStop) {
            $this->dispatchSignals();

            try {
                $data = $this->predisClient->blpop([$name], 2);
                $retryDelay = 1;
            } catch (Exception $e) {
                $this->log('[RedisQueue] Redis error: ' . $e->getMessage());
                $this->predisClient = null;
                $this->reconnectWithBackoff($retryDelay);
                $retryDelay = min($retryDelay * 2, 30);
                continue;
            }

            if ($data) {
                $message = $data[1];
                $decodedData = json_decode($message, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log('[RedisQueue] JSON decode error: ' . json_last_error_msg());
                    continue;
                }

                if (!empty($decodedData)) {
                    try {
                        $worker->do(new Message($decodedData));
                    } catch (Exception $e) {
                        $this->log('[RedisQueue] Worker error: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    protected function reconnectWithBackoff(int $retryDelay)
    {
        $this->log("[RedisQueue] Reconnecting in {$retryDelay}s...");

        for ($i = 0; $i < $retryDelay; $i++) {
            $this->dispatchSignals();
            if ($this->shouldStop) {
                return;
            }
            sleep(1);
        }

        try {
            $this->connectRedis();
            $this->log('[RedisQueue] Reconnected successfully');
        } catch (Exception $e) {
            $this->log('[RedisQueue] Reconnect failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function dispatchSignals()
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    protected function log($message)
    {
        if ($this->logger) {
            call_user_func($this->logger, $message);
        } else {
            error_log($message);
        }
    }
}
