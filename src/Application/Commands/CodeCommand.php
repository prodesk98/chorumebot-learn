<?php

namespace Chorume\Application\Commands;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Discord\Parts\Channel\Message;

class CodeCommand
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

    public function code(Interaction $interaction)
    {
        $interaction->respondWithMessage(
            $this->messageComposer->embed(
                'Aí manolo o código do bot tá aqui ó, não palpite, commit!',
                'https://github.com/brunofunnie/chorumebot'
            ),
        );
    }
}
