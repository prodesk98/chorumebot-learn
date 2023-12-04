<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\Talk;

class MessageCreate
{
    private $discord;
    private $config;
    private $redis;
    private Talk $talkRepository;

    public function __construct(
        $discord,
        $config,
        $redis,
        Talk $talkRepository
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->redis = $redis;
        $this->talkRepository = $talkRepository;
    }

    public function messageCreate(Message $message, Discord $discord)
    {
        if ($this->redis->get('talks')) {
            $textTriggers = json_decode($this->redis->get('talks'), true);
        } else {
            $textTriggers = $this->talkRepository->listAllTriggers();
            $this->redis->set('talks', json_encode($textTriggers), 'EX', 60);
        }

        if ($found = find_in_array(strtolower($message->content), 'triggertext', $textTriggers)) {
            $talk = $this->talkRepository->findById($found['id']);

            if (empty($talk)) {
                return;
            }

            $talkMessage = json_decode($talk[0]['answer']);

            switch ($talk[0]['type']) {
                case 'media':
                    $embed = $discord->factory(Embed::class);
                    $embed
                        ->setTitle($talkMessage->text)
                        ->setImage($talkMessage->image);
                    $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                    break;
                default:
                    $message->channel->sendMessage(MessageBuilder::new()->setContent($talkMessage->text));
                    break;
            }
        }
    }
}
