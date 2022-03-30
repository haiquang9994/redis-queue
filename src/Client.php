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

    public function loop(string $name, Woker $worker)
    {
        while (true) {
            $data = $this->predisClient->lpop($name);
            $data = @json_decode($data, true);
            if ($data) {
                if (!empty($data)) {
                    $worker->do(new Message($data));
                }
            } else {
                sleep(2);
            }
        }
    }
}
