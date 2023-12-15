<?php

namespace Chorume\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;

class TransferCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $fromDiscordId = $interaction->member->user->id;
        $coins = $interaction->data->options['coins']->value;
        $toDiscordId = $interaction->data->options['usuario']->value;
        $fromUser = $this->userRepository->getByDiscordId($fromDiscordId);
        $toUser = $this->userRepository->getByDiscordId($toDiscordId);
        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);

        $daysActiveAccount = (new \DateTime())->diff(new \DateTime($fromUser[0]['created_at']))->days;

        if ($coins <= 0 || $coins > 1000) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Quantidade inválida. Valor deve ser entre 1 e 1000 coins'), true);
            return;
        }

        if ($daysActiveAccount <= 15) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Sua conta no Chorume Coins precisa ter mais de 15 dias para transferir coins'), true);
            return;
        }

        if (!$this->userRepository->hasAvailableCoins($fromDiscordId, $coins)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não possui saldo suficiente'), true);
            return;
        }

        if ($fromDiscordId === $toDiscordId) {
            $this->userCoinHistoryRepository->create($fromUser[0]['id'], -$coins, 'Troll');

            $embed
                ->setTitle('TROLL')
                ->setColor('#44f520');

            $message = sprintf("Nossa mas você é engraçado mesmo né. Por ter sido troll por transferir para você mesmo, acabou de perder **%s** coins pela zoeira!\n\nInclusive tá todo mundo vendo essa merda aí que tu ta fazendo!\n\nHA! HA! HA! ENGRAÇADÃO! 👹👹👹", -$coins);
            $image = $this->config['images']['sefodeu'];

            $embed
                ->setDescription($message)
                ->setImage($image);

            $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), false);
            return;
        }

        if (!$this->userRepository->userExistByDiscordId($fromDiscordId)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Remetente não encontrado'), true);
            return;
        }

        if (!$this->userRepository->userExistByDiscordId($toDiscordId)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Beneficiário não encontrado'), true);
            return;
        }

        if (!$this->userCoinHistoryRepository->transfer($fromUser[0]['id'], $coins, $toUser[0]['id'])) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Erro inesperado ao transferir'), true);
            return;
        }

        $interaction->respondWithMessage(MessageBuilder::new()->setContent(sprintf("Você transferiu **%s** coins para <@%s>! :money_mouth: :money_mouth: :money_mouth:", $coins, $toDiscordId)), true);
    }
}
