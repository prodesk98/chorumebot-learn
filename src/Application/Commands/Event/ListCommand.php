<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;

class ListCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private Event $eventRepository,
        private EventChoice $eventChoiceRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $eventsOpen = $this->eventRepository->listEventsOpen();
        $eventsClosed = $this->eventRepository->listEventsClosed();
        $events = array_merge($eventsOpen, $eventsClosed);
        $ephemeralMsg = true;

        if (find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $ephemeralMsg = false;
        }

        $eventsDescription = "\n";

        if (empty($events)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não há eventos abertos!'), true);
        }

        foreach ($events as $event) {
            $eventOdds = $this->eventRepository->calculateOdds($event['event_id']);

            $eventsDescription .= sprintf(
                "**[#%s] %s** \n **Status: %s** \n **A**: %s \n **B**: %s \n \n",
                $event['event_id'],
                strtoupper($event['event_name']),
                $this->eventRepository::LABEL_LONG[(int) $event['event_status']],
                sprintf('%s (x%s)', $event['choices'][0]['choice_description'], number_format($eventOdds['oddsA'], 2)),
                sprintf('%s (x%s)', $event['choices'][1]['choice_description'], number_format($eventOdds['oddsB'], 2))
            );
        }

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle("EVENTOS")
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($this->config['images']['event']);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), $ephemeralMsg);
    }
}
