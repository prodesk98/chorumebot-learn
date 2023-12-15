<?php

namespace Chorume\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Voice\VoiceClient;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;
use Exception;

class CoinsCommand extends Command
{
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private int $cooldownSeconds;
    private int $cooldownTimes = 6;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->cooldownSeconds = getenv('COMMAND_COOLDOWN_TIMER');
        $this->cooldownTimes = getenv('COMMAND_COOLDOWN_LIMIT');
    }

    public function handle(Interaction $interaction): void
    {
        $interaction->acknowledgeWithResponse(true)->then(function () use ($interaction) {
            if (
                !$this->redisHelper->cooldown(
                    'cooldown:generic:coins:' . $interaction->member->user->id,
                    $this->cooldownSeconds,
                    $this->cooldownTimes
                )
            ) {
                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'EXTRATO DE COINS',
                        'Não vai nascer dinheiro magicamente na sua conta, seu liso! Aguarde 1 minuto para ver seu extrato!',
                        null,
                        '#FF0000'
                    )
                );
                return;
            }

            $discordId = $interaction->member->user->id;
            $user = $this->userRepository->getByDiscordId($discordId);

            if (empty($user)) {
                if ($this->userRepository->giveInitialCoins(
                    $interaction->member->user->id,
                    $interaction->member->user->username
                )) {
                    $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(sprintf(
                        'Você acabou de receber suas **%s** coins iniciais! Aposte com sabedoria :man_mage:',
                        100
                    )));
                }
            }

            $coinsQuery = $this->userRepository->getCurrentCoins($interaction->member->user->id);
            $currentCoins = $coinsQuery[0]['total'];
            $dailyCoins = 100;
            $message = '';

            if ($this->userRepository->canReceivedDailyCoins($interaction->member->user->id) && !empty($user)) {
                $currentCoins += $dailyCoins;
                $this->userRepository->giveDailyCoins($interaction->member->user->id, $dailyCoins);

                $message .= "Você recebeu suas **%s** coins diárias! :money_mouth:\n\n";
                $message = sprintf($message, $dailyCoins);
            }

            if ($currentCoins <= 0) {
                $message .= sprintf('Você não possui nenhuma coin, seu liso! :money_with_wings:', $currentCoins);
                $image = $this->config['images']['nomoney'];
            } elseif ($currentCoins > 1000) {
                $message .= sprintf('Você possui **%s** coins!! Tá faturando hein! :moneybag: :partying_face:', $currentCoins);
                $image = $this->config['images']['many_coins'];
            } else {
                $message .= sprintf('Você possui **%s** coins! :coin:', $currentCoins);
                $image = $this->config['images']['one_coin'];
            }

            $interaction->updateOriginalResponse(
                $this->messageComposer->embed(
                    'EXTRATO DE COINS',
                    $message,
                    $image,
                    '#F5D920'
                )
            );
        });
    }
}
