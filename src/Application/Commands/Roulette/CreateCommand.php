<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class CreateCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $value = $interaction->data->options['criar']->options['valor']->value;


        if ($this->rouletteRepository->createEvent(strtoupper($eventName), $value)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Roleta criado com sucesso! Valor por aposta: **C\${$value}**"), true);
        }
    }
}
