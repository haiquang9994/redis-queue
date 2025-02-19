<?php

namespace RedisQueue;

use Exception;
use Predis\Client as PredisClient;

class Client
{
    protected $host;
    protected $port;
    protected $predisClient;

    public function __construct($host = '127.0.0.1', $port = 6379)
    {
        $this->host = $host;
        $this->port = $port;
        $this->connectRedis();
    }

    protected function connectRedis()
    {
        $this->predisClient = new PredisClient([
            'host' => $this->host,
            'port' => $this->port,
        ]);
    }

    public function push(string $name, array $data)
    {
        $this->predisClient->rpush($name, json_encode($data));
        return $this;
    }

    public function loop(string $name, Worker $worker)
    {
        while (true) {
            try {
                $data = $this->predisClient->brpop([$name], 10);
            } catch (Exception $e) {
                $this->connectRedis();
            }

            if ($data) {
                $message = $data[1];
                $decodedData = json_decode($message, true);

                if (!empty($decodedData)) {
                    $worker->do(new Message($decodedData));
                }
            }
        }
    }
}
