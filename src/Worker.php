<?php

namespace RedisQueue;

abstract class Worker
{
    abstract public function do(Message $message);
}
