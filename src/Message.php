<?php

namespace RedisQueue;

class Message
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __get($key)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : null;
    }
}
