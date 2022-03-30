<?php

namespace RedisQueue;

abstract class Woker
{
    abstract public function do(Message $message);
}
