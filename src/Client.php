<?php

namespace RedisQueue;

use Predis\Client as PredisClient;

class Client
{
    protected $predisClient;

    public function __construct($host = '127.0.0.1', $port = 6379)
    {
        $this->predisClient = new PredisClient([
            'host'   => $host,
            'port'   => $port,
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
            $data = $this->predisClient->brpop([$name], 10);

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
