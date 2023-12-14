<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Event;

class CreateCommand extends Command
{
    public function __construct(
        public Discord $discord,
        public $config,
        public Event $eventRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $optionA = $interaction->data->options['criar']->options['a']->value;
        $optionB = $interaction->data->options['criar']->options['b']->value;

        if ($this->eventRepository->create(strtoupper($eventName), $optionA, $optionB)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento criado com sucesso!'), true);
        }
    }
}
