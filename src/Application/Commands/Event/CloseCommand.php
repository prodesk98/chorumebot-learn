<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;

class CloseCommand extends Command
{
    public function __construct(
        public Discord $discord,
        public $config,
        public Event $eventRepository,
        public EventChoice $eventChoiceRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventId = $interaction->data->options['fechar']->options['id']->value;
        $event = $this->eventRepository->getEventById($eventId);

        if (empty($event)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Evento **#%s** não existe!', $eventId)
                ),
                false
            );
            return;
        }

        if (!$this->eventRepository->closeEvent($eventId)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Ocorreu um erro ao finalizar evento **#%s**', $eventId)
                ),
                false
            );
            return;
        }

        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(
                sprintf('Evento **#%s** fechado! Esse evento não recebe mais apostas!', $eventId)
            ),
            false
        );
        return;
    }
}
