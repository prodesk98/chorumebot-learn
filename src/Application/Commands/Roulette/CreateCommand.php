<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Commands\Roulette\RouletteBuilder;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class CreateCommand extends Command
{
    private RouletteBuilder $rouletteBuilder;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
        $this->rouletteBuilder = new RouletteBuilder(
            $this->discord,
            $this->config,
            $this->redis,
            $this->userRepository,
            $this->rouletteRepository,
            $this->rouletteBetRepository
        );
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $value = $interaction->data->options['criar']->options['valor']->value;

        $this->createRoulette($interaction, $eventName, $value);
    }

    public function createRoulette(Interaction $interaction, string $eventName, float $value): void
    {
        $rouletteId = $this->rouletteRepository->createEvent(strtoupper($eventName), $value);

        if (!$rouletteId) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Não foi possível criar a roleta!"), true);
            return;
        }

        if ($value < 0 || $value < 1) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Só é possível criar roletas a partir de 1 coin!"), true);
            return;
        }

        $this->rouletteBuilder->build($interaction, $rouletteId);
    }
}
