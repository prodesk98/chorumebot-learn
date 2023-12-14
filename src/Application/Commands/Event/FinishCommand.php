<?php

namespace Chorume\Application\Commands\Event;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;

class FinishCommand extends Command
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

        $eventId = $interaction->data->options['encerrar']->options['id']->value;
        $choiceKey = $interaction->data->options['encerrar']->options['opcao']->value;
        $event = $this->eventRepository->getEventById($eventId);
        $choice = $this->eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $choiceKey);
        $bets = $this->eventRepository->payoutEvent($eventId, $choiceKey);

        if (empty($event)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento não existe!'), true);
            return;
        }

        if ($event[0]['status'] !== $this->eventRepository::CLOSED) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento precisa estar fechado para ser finalizado!'), true);
            return;
        }

        if (count($bets) === 0) {
            $this->eventRepository->finishEvent($eventId);
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento encerrado não houveram apostas!'), true);
            return;
        }

        $eventsDescription = sprintf(
            "**Evento:** %s \n **Vencedor**: %s \n \n \n",
            $event[0]['name'],
            $choice[0]['description'],
        );

        $winnersImage = $this->config['images']['winners'][array_rand($this->config['images']['winners'])];

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle(sprintf('EVENTO #%s ENCERRADO', $eventId))
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($winnersImage);

        $winners = '';
        $earnings = '';

        foreach ($bets as $bet) {
            if ($bet['choice_key'] == $choiceKey) {
                $winners .= sprintf("<@%s> \n", $bet['discord_user_id']);
                $earnings .= sprintf("%s \n", $bet['earnings']);
            }
        }

        $embed
            ->addField([ 'name' => 'Ganhador', 'value' => $winners, 'inline' => 'true' ])
            ->addField([ 'name' => 'Valor', 'value' => $earnings, 'inline' => 'true' ]);

        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed));
    }
}
