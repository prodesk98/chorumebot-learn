<?php

namespace Chorume\Application\Commands\Test;

use GuzzleHttp\Client;

use Predis\Client as RedisClient;
use Discord\Discord;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Parts\Interactions\Interaction;

class TestCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {

    }
}
