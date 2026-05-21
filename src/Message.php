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
        return $this->get($key);
    }

    public function __isset($key)
    {
        return $this->has($key);
    }

    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
