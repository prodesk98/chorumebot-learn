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
                        title: 'Suas coins',
                        message: 'Não vai brotar dinheiro do nada! Aguarde 1 min para ver seu extrato!',
                        color: '#FF0000',
                        thumbnail: $this->config['images']['steve_no']
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
                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            title: 'Bem vindo',
                            message: 'Você recebeu **100** coins iniciais! Aposte sabiamente :man_mage:',
                            color: '#F5D920',
                            thumbnail: $this->config['images']['one_coin']
                        )
                    );

                    return;
                }
            }

            $coinsQuery = $this->userRepository->getCurrentCoins($interaction->member->user->id);
            $currentCoins = $coinsQuery[0]['total'];
            $dailyCoins = 100;
            $message = '';

            if ($this->userRepository->canReceivedDailyCoins($interaction->member->user->id) && !empty($user)) {
                $currentCoins += $dailyCoins;
                $this->userRepository->giveDailyCoins($interaction->member->user->id, $dailyCoins);

                $message .= "**+%s diárias**\n";
                $message = sprintf($message, $dailyCoins);
            }

            $message .= sprintf('**%s** coins', $currentCoins);
            $image = $this->config['images']['one_coin'];

            $interaction->updateOriginalResponse(
                $this->messageComposer->embed(
                    title: 'Saldo',
                    message: $message,
                    color: $currentCoins === 0 ? '#FF0000' : '#F5D920',
                    thumbnail: $image
                )
            );
        });
    }
}
