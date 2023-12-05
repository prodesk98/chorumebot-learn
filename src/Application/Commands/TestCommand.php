<?php

namespace Chorume\Application\Commands;

use GuzzleHttp\Client;

use Predis\Client as RedisClient;
use Discord\Discord;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Parts\Interactions\Interaction;

class TestCommand
{
    private Discord $discord;
    private $config;
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;

    public function __construct(
        Discord $discord,
        $config,
        RedisClient $redis
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function test(Interaction $interaction)
    {

    }
}
