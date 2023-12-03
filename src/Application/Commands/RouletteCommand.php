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
    public $jogadores;
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

        if ($this->rouletteRepository->createEvent(strtoupper($eventName))) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta criado com sucesso!'), true);
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
                "**[#%s] %s** \n **Status: %s** \n \n \n",
                $event['roulette_id'],
                strtoupper($event['description']),
                $this->rouletteRepository::LABEL_LONG[(int) $event['status']],
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
            ->setImage($this->config['images']['event']);
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
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
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
        } else if ($winnerNumber % 2 == 0) {
            $winnerResult = Roulette::BLACK;
            $choice = "⬛ [$winnerNumber] BLACK [$winnerNumber] ⬛";
        } else {
            $winnerResult = Roulette::RED;
            $choice = "🟥  [$winnerNumber] RED [$winnerNumber] 🟥";
        }



        $bets = $this->rouletteRepository->payoutRoulette($eventId, $winnerResult);

        $events = $this->rouletteRepository->listEventsClosed();

        if (empty($event)) {
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent('Roleta não existe!'), true);
            return;
        }

        if ($event[0]['status'] !== $this->rouletteRepository::CLOSED) {
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent('Roleta precisa estar fechada para ser Girada!'), true);
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
            ->setTitle(sprintf('ROLETA #%s ENCERRADA', $eventId))
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
        
        $embed
            ->addField(['name' => 'Ganhador(es) / Valor $', 'value' => $earningsString, 'inline' => 'true']);  
        



        $builder = new MessageBuilder();

        $descriptions = [
            "https://i.imgur.com/0h7iY0w.png",
            "https://i.imgur.com/sXwdVwj.png",
            "https://i.imgur.com/QlINCFE.png",
            "https://i.imgur.com/Fi7tCZJ.png",
            "https://i.imgur.com/rBscYWA.png",
            "https://i.imgur.com/h0ClHqm.png",
            "https://i.imgur.com/cAc2GFA.png",
            "https://i.imgur.com/55ZYwtC.png",
            "https://i.imgur.com/W0AXOuN.png",
            "https://i.imgur.com/jxHrthT.png",
            "https://i.imgur.com/fUIJPcU.png",
            "https://i.imgur.com/oAMzXY8.png",
            "https://i.imgur.com/xiftlWC.png",
            "https://i.imgur.com/M8t90KG.png",
            "https://i.imgur.com/GoRnW6c.png"
        ];
        $gif = "https://i.imgur.com/Pul3tnz.gif";

        if (count($bets) === 0) {

            $embednovo = new Embed($this->discord);
            $embednovo
                ->setTitle(sprintf('ROLETA #%s ENCERRADA', $eventId))
                ->setColor('#F5D920')
                ->setDescription("**Resultado**: Não teve Vencedores.");

            $embed = $embednovo;
        }
        $embedLoop = new Embed($this->discord);
        $embedLoop->setImage($gif);
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


    function expose(Interaction $interaction)
    {
        $rouletteId = $interaction->data->options['apostar']->options['id']->value;
      
        $builder = MessageBuilder::new();
        $action = ActionRow::new();
        $AmountBet = 100;
        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
        $embed = new Embed($this->discord);

        if (empty($roulette)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta não existe!'), true);
            return;
        }
        
        if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'), true);
            return;
        }


        $gameData = $this->redis->get("{$rouletteId}");
        $gameData = unserialize($gameData);

        if ($gameData != null) {
        } else {
            $gameData = new GameData($rouletteId);
            $serializado =  serialize($gameData);
            $this->redis->set("{$rouletteId}", $serializado);
        }


        $button1 = Button::new(Button::STYLE_DANGER)->setLabel('RED +100')->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $AmountBet, &$gameData) {
            $fromDiscordId = $interactionUser->member->user->id;
            $userDiscord = $interactionUser->member->user;
            $this->apostarRoleta($fromDiscordId, Roulette::RED, $rouletteId, $interaction, $roulette, $AmountBet,  $gameData, $userDiscord, $interactionUser);
        }, $this->discord);

        $button2 = Button::new(Button::STYLE_SUCCESS)->setLabel('GREEN +100')->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $AmountBet, &$gameData) {
            $fromDiscordId = $interactionUser->member->user->id;
            $userDiscord = $interactionUser->member->user;


            $this->apostarRoleta($fromDiscordId, Roulette::GREEN, $rouletteId, $interaction, $roulette, $AmountBet, $gameData, $userDiscord, $interactionUser);
        }, $this->discord);

        $button3 = Button::new(Button::STYLE_SECONDARY)->setLabel('BLACK +100')->setListener(function (Interaction $interactionUser) use ($interaction, $rouletteId, $roulette, $AmountBet, &$gameData) {
            $fromDiscordId = $interactionUser->member->user->id;
            $userDiscord = $interactionUser->member->user;
            $this->apostarRoleta($fromDiscordId, Roulette::BLACK, $rouletteId, $interaction, $roulette, $AmountBet, $gameData, $userDiscord, $interactionUser);
        }, $this->discord);

        $action->addComponent($button1);
        $action->addComponent($button2);
        $action->addComponent($button3);



        $embed = $this->buildEmbedForRoulette($rouletteId, $roulette, $gameData);

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);


        $builder->addComponent($action);


        $interaction->respondWithMessage($builder, false);
    }

    function apostarRoleta($userDiscordId, $choice, $rouletteId, Interaction $interaction, $roulette, $AmountBet, &$gameData, $userDiscord, Interaction $interactionUser)

    {

        $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
            if ($roulette[0]['status'] !== $this->rouletteRepository::OPEN) {
                $interactionUser->respondWithMessage(MessageBuilder::new()->setContent('Roleta precisa estar aberta para apostar!'), true);
                return;
        }

        $user = $this->userRepository->getByDiscordId($userDiscordId);
        $userId = $user[0]['id'];

        if (!$this->userRepository->hasAvailableCoins($userDiscordId, $AmountBet)) {
            $interactionUser->respondWithMessage(MessageBuilder::new()->setContent('Você não possui coins suficientes! :crying_cat_face:'), true);
            return;
        }

        if ($choice == Roulette::RED)
            $gameData->AmountRed = $gameData->AmountRed + $AmountBet;
        else if ($choice == Roulette::GREEN)
            $gameData->AmountGreen = $gameData->AmountGreen + $AmountBet;
        else
            $gameData->AmountBlack = $gameData->AmountBlack + $AmountBet;


        $gameData->AmountTotal = $gameData->AmountTotal + $AmountBet;

        $player = new Player($userId, $AmountBet, $choice, $userDiscord);
        $playerFound = false;
        foreach ($gameData->jogadores as &$existingPlayer) {
            if ($existingPlayer->user == $userId && $existingPlayer->choice === $choice) {
                $existingPlayer->bet = $existingPlayer->bet + $AmountBet;
                $playerFound = true;
                break;
            }
        }
        if (!$playerFound) {
            $gameData->jogadores[] = $player;
        }
        $serializado =  serialize($gameData);
        $this->redis->set("{$rouletteId}", $serializado);


        if ($this->rouletteBetRepository->createRouletteBet($userId, $rouletteId, $AmountBet, $choice)) {
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
        $embed->setTitle("💰APOSTEM NA ROLETA: #{$rouletteId} {$roulette[0]['description']}💰")
            ->setColor(0x00ff00)
            ->setDescription("Total: {$gameData->AmountTotal}")
            ->setFooter('💰💰CHORULETTA💰💰');

        $embed->addFieldValues('🟥   RED  🟥 ', '', true)
            ->addFieldValues('🟩  GREEN  🟩', '', true)
            ->addFieldValues('⬛  BLACK  ⬛', '', true);


        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "Nome: {$player->userName}\nBet: {$player->bet}";
            }, $playersRed)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "Nome: {$player->userName}\nBet: {$player->bet}";
            }, $playersGreen)),
            true
        );

        $embed->addFieldValues(
            '',
            implode("\n", array_map(function ($player) {
                return "Nome: {$player->userName}\nBet: {$player->bet}";
            }, $playersBlack)),
            true
        );

        return $embed;
    }
}
