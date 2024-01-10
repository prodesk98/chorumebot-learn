<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\Talk;

class MessageCreate extends Event
{
    public function __construct(
        private $discord,
        private $config,
        private $redis,
        private Talk $talkRepository
    ) {
    }

    public function handle(Message $message): void
    {
        if ($this->redis->get('talks')) {
            $textTriggers = json_decode($this->redis->get('talks'), true);
        } else {
            $textTriggers = $this->talkRepository->listAllTriggers();
            $this->redis->set('talks', json_encode($textTriggers), 'EX', 60);
        }

        $found = find_in_array(strtolower($message->content), 'triggertext', $textTriggers);

        if ($found) {
            $talk = $this->talkRepository->findById($found['id']);

            if (empty($talk)) {
                return;
            }

            $talkMessage = json_decode($talk[0]['answer']);

            switch ($talk[0]['type']) {
                case 'media':
                    $embed = new Embed($this->discord);
                    $embed->setTitle($talkMessage->text);

                    if ($talkMessage->description) {
                        $embed->setDescription($talkMessage->description);
                    }

                    $embed->setImage($talkMessage->image);

                    $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                    break;
                default:
                    $message->channel->sendMessage(MessageBuilder::new()->setContent($talkMessage->text));
                    break;
            }
        }
    }
}
