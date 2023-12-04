<?php

namespace Chorume\Application\Commands;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Discord\Parts\Embed\Embed;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\ActionRow;
use Chorume\Repository\User;
use Predis\Client as RedisClient;
use function PHPSTORM_META\map;
use function RingCentral\Psr7\copy_to_string;

class Player
{
    public $user;
    public int $bet;
    public $choice;
    public $userName;

    public function __construct($user, $bet, $choice, $userName)
    {
        $this->user = $user;
        $this->bet = $bet;
        $this->choice = $choice;
        $this->userName = $userName;
    }
}

class GameData
{
    public int $AmountRed = 0;
    public int $AmountGreen = 0;
    public int $AmountBlack = 0;
    public int $AmountTotal = 0;
    public array $jogadores;
    public int $rouletteId = 0;

    public function __construct($rouletteId)
    {
        $this->AmountRed = 0;
        $this->AmountGreen = 0;
        $this->AmountBlack = 0;
        $this->AmountTotal = 0;
        $this->jogadores = [];
        $this->rouletteId = $rouletteId;
    }
}

class RouletteCommand
{
    public $discord;
    public $config;
    public Roulette $rouletteRepository;
    public RouletteBet $rouletteBetRepository;
    public User $userRepository;
    private RedisClient $redis;

    public function __construct(
        Discord $discord,
        $config,
        Roulette $rouletteRepository,
        RouletteBet $rouletteBetRepository,
        User $userRepository,
        RedisClient $redis,
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->rouletteRepository = $rouletteRepository;
        $this->rouletteBetRepository = $rouletteBetRepository;
        $this->userRepository = $userRepository;
        $this->redis = $redis;
    }

    public function create(Interaction $interaction)
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $eventName = $interaction->data->options['criar']->options['nome']->value;
        $value = $interaction->data->options['criar']->options['valor']->value;


        if ($this->rouletteRepository->createEvent(strtoupper($eventName), $value)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent("Roleta criado com sucesso! {$value} C$"), true);
        }
    }

    public function list(Interaction $interaction)
    {
        $roulettesOpen = $this->rouletteRepository->listEventsOpen();
        $roulettesClosed = $this->rouletteRepository->listEventsClosed();

        if (!is_array($roulettesOpen)) {
            $roulettesOpen = [];
        }

        if (!is_array($roulettesClosed)) {
            $roulettesClosed = [];
        }

        $roulettes = array_merge($roulettesOpen, $roulettesClosed);
        $ephemeralMsg = true;

        if (find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $ephemeralMsg = false;
        }

        $roulettesDescription = "\n";

        if (empty($roulettes)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não há Roletas abertas!'), true);
        }

        foreach ($roulettes as $event) {

            $roulettesDescription .= sprintf(
                "**[#%s] %s** \n **Status: %s C$ %s** \n \n \n",
                $event['roulette_id'],
                strtoupper($event['description']),
                $this->rouletteRepository::LABEL_LONG[(int) $event['status']],
                strtoupper($event['amount']),
                sprintf(''),
                sprintf('')
            );
        }

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle("ROLETAS")
            ->setColor('#F5D920')
            ->setDescription($roulettesDescription)
            ->setImage($this->config['images']['roulette']['list']);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), $ephemeralMsg);
    }

    public function close(Interaction $interaction)
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $rouletteId = $interaction->data->options['fechar']->options['id']->value;
        $event = $this->rouletteRepository->getRouletteById($rouletteId);

        if ($event[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta precisa estar aberta para ser fechada!'), true);
            return;
        }

        if (empty($event)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Roleta **#%s** não existe!', $rouletteId)
                ),
                false
            );
            return;
        }

        if (!$this->rouletteRepository->closeEvent($rouletteId)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    sprintf('Ocorreu um erro ao finalizar Roleta **#%s**', $rouletteId)
                ),
                false
            );
            return;
        }

        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(
                sprintf('Roleta **#%s** fechada! Esse evento não recebe mais apostas!', $rouletteId)
            ),
            false
        );

        return;
    }

    public function finish(Interaction $interaction)
    {
        $interaction->acknowledgeWithResponse(false);

        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                true
            );
            return;
        }

        $eventId = $interaction->data->options['girar']->options['id']->value;
        $event = $this->rouletteRepository->getRouletteById($eventId);
        $winnerNumber = rand(0, 14);
        $winnerResult = null;
        $choice = null;

        if ($winnerNumber == 0) {
            $winnerResult = Roulette::GREEN;
            $choice = "🟩 [$winnerNumber] GREEN [$winnerNumber] 🟩";
        } elseif ($winnerNumber % 2 == 0) {
            $winnerResult = Roulette::BLACK;
            $choice = "⬛ [$winnerNumber] BLACK [$winnerNumber] ⬛";
        } else {
            $winnerResult = Roulette::RED;
            $choice = "🟥  [$winnerNumber] RED [$winnerNumber] 🟥";
        }

        $bets = $this->rouletteRepository->payoutRoulette($eventId, $winnerResult);

        if (empty($event)) {
            $interaction->updateOriginalResponse(
                MessageBuilder::new()->setContent('Roleta não existe!'),
                true
            );
            return;
        }

        if ($event[0]['status'] !== $this->rouletteRepository::CLOSED) {
            $interaction->updateOriginalResponse(
                MessageBuilder::new()->setContent('Roleta precisa estar fechada para ser Girada!'),
                true
            );
            return;
        }

        $eventsDescription = sprintf(
            "**Evento:** %s \n **Vencedor**: %s \n \n \n",
            $event[0]['description'],
            "{$choice}",
        );

        $winnersImage = $this->config['images']['winners'][array_rand($this->config['images']['winners'])];

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
        $embed
            ->setTitle(sprintf("ROLETA ENCERRADA\n[%s] %s", $eventId, $event[0]['description']))
            ->setColor('#F5D920')
            ->setDescription($eventsDescription)
            ->setImage($winnersImage);

        $earningsByUser = [];
        $winners = '';
        $earningsString = '';

        foreach ($bets as $bet) {
            if ($bet['choice_key'] == $winnerResult) {
                if (!isset($earningsByUser[$bet['discord_user_id']])) {
                    $earningsByUser[$bet['discord_user_id']] = 0;
                }
                $earningsByUser[$bet['discord_user_id']] += intval($bet['earnings']);
            }
        }

        foreach ($earningsByUser as $userId => $earnings) {
            $winners .= sprintf("<@%s> \n", $userId);
            $earningsString .= sprintf("<@%s>: %s 🪙\n", $userId, $earnings);
        }

        $embed->addField([
            'name' => 'Premiação / Valor C$',
            'value' => $earningsString,
            'inline' => 'true'
        ]);

        $descriptions = $this->config['images']['roulette']['numbers'];
        $imageRouletteSpin = $this->config['images']['roulette']['spin'];

        if (count($bets) === 0) {
            $embednovo = new Embed($this->discord);
            $embednovo
                ->setTitle(sprintf('ROLETA #%s ENCERRADA', $eventId))
                ->setColor('#F5D920')
                ->setDescription("**Resultado**: Não houveram vencedores.");

            $embed = $embednovo;
        }

        $embedLoop = new Embed($this->discord);
        $embedLoop->setImage($imageRouletteSpin);
        $embedLoop->setTitle(sprintf('ROLETA #%s ENCERRADA', $eventId));
        $embedLoop->setDescription("**Sorteando um número!**");

        $builderLoop = new MessageBuilder();
        $builderLoop->addEmbed($embedLoop);
        $interaction->updateOriginalResponse($builderLoop, false);

        $loop = $this->discord->getLoop();
        $loop->addTimer(8, function () use ($embed, $interaction, $descriptions, $winnerNumber) {
            $embed->setImage($descriptions[$winnerNumber]);
            $builder = new MessageBuilder();
            $builder->addEmbed($embed);
            $interaction->updateOriginalResponse($builder, false);
        });
    }

    public function expose(Interaction $interaction)
    {
        $rouletteId = $interaction->data->options['apostar']->options['id']->value;
        $builder = MessageBuilder::new();
        $action = ActionRow::new();
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
        $amountBet = (int)$roulette[0]['amount'];
        $embed = new Embed($this->discord);

        if (empty($roulette)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta não existe!'),
                true
            );
            return;
        }

        if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'),
                true
            );
            return;
        }

        $gameData = $this->redis->get($rouletteId);
        $gameData = unserialize($gameData ?? '');

        if (!$gameData) {
            $gameData = new GameData($rouletteId);
            $serializado = serialize($gameData);
            $this->redis->set("{$rouletteId}", $serializado);
        }

        var_dump($gameData);

        $buttonRed = Button::new(Button::STYLE_DANGER)->setLabel("RED +{$amountBet}")->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
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
        }, $this->discord);

        $buttonGreen = Button::new(Button::STYLE_SUCCESS)->setLabel("GREEN +{$amountBet}")->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
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
        }, $this->discord);

        $buttonBlack = Button::new(Button::STYLE_SECONDARY)->setLabel("BLACK +{$amountBet}")->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $amountBet, &$gameData) {
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
        }, $this->discord);

        $action->addComponent($buttonRed);
        $action->addComponent($buttonGreen);
        $action->addComponent($buttonBlack);

        $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);
        $builder->addComponent($action);

        $interaction->respondWithMessage($builder, false);
    }

    public function apostarRoleta(
        $userDiscordId,
        $choice,
        $rouletteId,
        Interaction $interaction,
        $roulette,
        $amountBet,
        &$gameData,
        $userDiscord,
        Interaction $interactionUser
    )
    {
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);

        if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interactionUser->respondWithMessage(MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'), true);
            return;
        }

        $user = $this->userRepository->getByDiscordId($userDiscordId);
        $userId = $user[0]['id'];

        if (!$this->userRepository->hasAvailableCoins($userDiscordId, $amountBet)) {
            $interactionUser->respondWithMessage(MessageBuilder::new()->setContent('Você não possui coins suficientes! :crying_cat_face:'), true);
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
        $player = new Player($userId, $amountBet, $choice, $userDiscord);
        $playerFound = false;

        foreach ($gameData->jogadores as &$existingPlayer) {
            if ($existingPlayer->user == $userId && $existingPlayer->choice === $choice) {
                $existingPlayer->bet = $existingPlayer->bet + $amountBet;
                $playerFound = true;
                break;
            }
        }

        if (!$playerFound) {
            $gameData->jogadores[] = $player;
        }

        $serializado = serialize($gameData);
        $this->redis->set("{$rouletteId}", $serializado);

        if ($this->rouletteBetRepository->createRouletteBet($userId, $rouletteId, $amountBet, $choice)) {
            $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

            $builder = new MessageBuilder();
            $builder->addEmbed($embed);
            $interaction->updateOriginalResponse($builder);
        }
    }

    public function buildEmbedForRoulette($rouletteId, $roulette, GameData &$gameData)
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
        $embed->setTitle("💰 APOSTEM NA ROLETA: 💰\n[#{$rouletteId}] {$roulette[0]['description']}")
            ->setColor(0x00ff00)
            ->setDescription("Total: {$gameData->AmountTotal}")
            ->setFooter('💰💰 CHORULETTA 💰💰');

        $embed->addFieldValues('🟥  RED  🟥 2x', '', true)
            ->addFieldValues('🟩 GREEN 🟩 14x', '', true)
            ->addFieldValues('⬛ BLACK ⬛ 2x', '', true);

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
}
