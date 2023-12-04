<?php

namespace Chorume\Application\Commands;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;

class EventsCommand
{
    public $discord;
    public $config;
    public Event $eventRepository;
    public EventChoice $eventChoiceRepository;

    public function __construct(
        Discord $discord,
        $config,
        EventChoice $eventChoiceRepository,
        Event $eventRepository
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->eventRepository = $eventRepository;
        $this->eventChoiceRepository = $eventChoiceRepository;
    }

    public function create(Interaction $interaction)
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

    public function close(Interaction $interaction)
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

    public function finish(Interaction $interaction)
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

    public function list(Interaction $interaction)
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

    public function advertise(Interaction $interaction)
    {
        $eventId = $interaction->data->options['anunciar']->options['id']->value;
        $bannerKey = $interaction->data->options['anunciar']->options['banner']->value;

        $event = $this->eventRepository->listEventById($eventId);

        if (empty($event)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Esse evento não existe!'), true);
            return;
        }

        $eventOdds = $this->eventRepository->calculateOdds($eventId);
        $eventsDescription = sprintf(
            "**Status do Evento:** %s \n **A**: %s \n **B**: %s \n \n",
            $this->eventRepository::LABEL[$event[0]['event_status']],
            sprintf('%s (x%s)', $event[0]['choices'][0]['choice_description'], number_format($eventOdds['oddsA'], 2)),
            sprintf('%s (x%s)', $event[0]['choices'][1]['choice_description'], number_format($eventOdds['oddsB'], 2))
        );

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle(sprintf('[#%s] %s', $event[0]['event_id'], $event[0]['event_name']))
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($this->config['images']['events'][$bannerKey]);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), false);
    }
}
