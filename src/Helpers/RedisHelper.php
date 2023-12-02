<?php

namespace Chorume\Helpers;

class RedisHelper
{
    private $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function cooldown(string $key, int $seconds = 60): bool
    {
        if ($curThreshold = $this->redis->get($key)) {
            if ($curThreshold < 2) {
                $this->redis->set($key, ++$curThreshold, 'EX', $seconds);
                return true;
            }

            return false;
        }

        return true;
    }
}