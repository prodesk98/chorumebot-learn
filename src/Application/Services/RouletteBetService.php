<?php

namespace Chorume\Application\Services;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;

class RouletteBetService
{
    public $discord;
    public $config;
    public Roulette $rouletteRepository;
    public RouletteBet $rouletteBetRepository;

    public function __construct(
        Discord $discord,
        $config,
        Roulette $rouletteRepository,
        RouletteBet $rouletteBetRepository
    )
    {
        $this->discord = $discord;
        $this->config = $config;
        $this->rouletteRepository = $rouletteRepository;
        $this->rouletteBetRepository = $rouletteBetRepository;
    }

    public function criar(Interaction $interaction)
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;

        if ($this->rouletteRepository->createEvent(strtoupper($eventName))) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta criado com sucesso!'), true);
        }
    }
}
