<?php

namespace Chorume\Application\Commands\Roulette;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Roulette\GameData;
use Chorume\Application\Commands\Roulette\Player;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;

class RouletteBuilder
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

    public function build(Interaction $interaction, int $rouletteId): void
    {
        $builder = MessageBuilder::new();
        $action = ActionRow::new();
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
        $amountBet = (int) $roulette[0]['amount'];
        $status = (int) $roulette[0]['status'];
        $embed = new Embed($this->discord);

        if (empty($roulette)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta não existe!'),
                true
            );
            return;
        }

        if ($status !== $this->rouletteRepository::OPEN) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'),
                true
            );
            return;
        }

        $gameDataCache = $this->redis->get("roulette:{$rouletteId}");
        $gameData = unserialize($gameDataCache ?? '');

        if (!$gameData) {
            $gameData = new GameData($rouletteId);
            $serializado = serialize($gameData);
            $this->redis->set("roulette:{$rouletteId}", $serializado);
        }

        $buttonRed = Button::new(Button::STYLE_DANGER)
                        ->setLabel("R +{$amountBet}")
                        ->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                            $fromDiscordId = $interactionUser->member->user->id;
                            $userDiscord = $interactionUser->member->user;

                            $this->apostarRoleta(
                                $fromDiscordId,
                                Roulette::RED,
                                $rouletteId,
                                $interaction,
                                $roulette,
                                $amountBet,
                                $gameData,
                                $userDiscord,
                                $interactionUser
                            );
                        },
                        $this->discord
                    );

        $buttonGreen = Button::new(Button::STYLE_SUCCESS)
                            ->setLabel("G +{$amountBet}")
                            ->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                                $fromDiscordId = $interactionUser->member->user->id;
                                $userDiscord = $interactionUser->member->user;

                                $this->apostarRoleta(
                                    $fromDiscordId,
                                    Roulette::GREEN,
                                    $rouletteId,
                                    $interaction,
                                    $roulette,
                                    $amountBet,
                                    $gameData,
                                    $userDiscord,
                                    $interactionUser
                                );
                            },
                            $this->discord
                        );

        $buttonBlack = Button::new(Button::STYLE_SECONDARY)
                            ->setLabel("BL +{$amountBet}")
                            ->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
                                $fromDiscordId = $interactionUser->member->user->id;
                                $userDiscord = $interactionUser->member->user;

                                $this->apostarRoleta(
                                    $fromDiscordId,
                                    Roulette::BLACK,
                                    $rouletteId,
                                    $interaction,
                                    $roulette,
                                    $amountBet,
                                    $gameData,
                                    $userDiscord,
                                    $interactionUser
                                );
                            },
                            $this->discord
                        );

        $action->addComponent($buttonRed);
        $action->addComponent($buttonGreen);
        $action->addComponent($buttonBlack);

        $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);
        $builder->addComponent($action);

        $interaction->respondWithMessage($builder, false);
    }

    private function apostarRoleta(
        $userDiscordId,
        $choice,
        $rouletteId,
        Interaction $interaction,
        $roulette,
        $amountBet,
        &$gameData,
        $userDiscord,
        Interaction $interactionUser
    ): void
    {
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);

        if (!$this->userRepository->userExistByDiscordId($userDiscordId)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Você ainda não coleteu suas coins iniciais! Digita **/coins** e pegue suas coins! :coin::coin::coin: '
                ),
                true
            );
            return;
        }

        if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interactionUser->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Roleta precisa estar aberta para apostar!'),
                    true
                );
            return;
        }

        $user = $this->userRepository->getByDiscordId($userDiscordId);
        $userId = $user[0]['id'];

        if (!$this->userRepository->hasAvailableCoins($userDiscordId, $amountBet)) {
            $interactionUser->respondWithMessage(
                MessageBuilder::new()->setContent(
                    'Você não possui coins suficientes! :crying_cat_face:'),
                    true
                );
            return;
        }

        if ($choice == Roulette::RED) {
            $gameData->AmountRed = $gameData->AmountRed + $amountBet;
        } elseif ($choice == Roulette::GREEN) {
            $gameData->AmountGreen = $gameData->AmountGreen + $amountBet;
        } else {
            $gameData->AmountBlack = $gameData->AmountBlack + $amountBet;
        }

        $gameData->AmountTotal = $gameData->AmountTotal + $amountBet;
        $playerFound = false;

        foreach ($gameData->jogadores as &$existingPlayer) {
            if ((int) $existingPlayer->user === (int) $userId && (int) $existingPlayer->choice === (int) $choice) {
                $existingPlayer->bet = $existingPlayer->bet + $amountBet;
                $playerFound = true;
                break;
            }
        }

        $player = new Player($userId, $amountBet, $choice, $userDiscord);

        if (!$playerFound) {
            $gameData->jogadores[] = $player;
        }

        $serializado = serialize($gameData);
        $this->redis->set("roulette:{$rouletteId}", $serializado);

        if ($this->rouletteBetRepository->createRouletteBet($userId, $rouletteId, $amountBet, $choice)) {
            $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

            $builder = new MessageBuilder();
            $builder->addEmbed($embed);
            $interaction->updateOriginalResponse($builder);
        }
    }

    private function buildEmbedForRoulette($rouletteId, $roulette, GameData &$gameData): Embed
    {
        $playersRed = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::RED;
        });

        $playersGreen = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::GREEN;
        });

        $playersBlack = array_filter($gameData->jogadores, function ($player) {
            return $player->choice == Roulette::BLACK;
        });

        $embed = new Embed($this->discord);
        $embed->setTitle("APOSTEM NA ROLETA 💰\n**[#{$rouletteId}]** {$roulette[0]['description']}")
            ->setColor(0x00ff00)
            ->setDescription("Total: {$gameData->AmountTotal}")
            ->setFooter("Últimos giros:\n" . $this->buildLastRoulettesChoices());

        $embed->addFieldValues('🟥 RED 2x', '', true)
            ->addFieldValues('🟩 GREEN 14x', '', true)
            ->addFieldValues('⬛ BLACK 2x', '', true);

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersRed)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersGreen)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "{$player->userName}\nBet: {$player->bet}";
            }, $playersBlack)),
            true
        );

        return $embed;
    }

    private function buildLastRoulettesChoices(): string
    {
        $lastRoulettes = $this->rouletteRepository->listEventsPaid(15);

        $choices = array_map(function ($arr) {
            return match ($arr['choice']) {
                Roulette::GREEN => "🟩",
                Roulette::BLACK => "⬛",
                Roulette::RED => "🟥",
                null => ""
            };
        }, $lastRoulettes);

        return '➡' . implode(' ', $choices);
    }

}
