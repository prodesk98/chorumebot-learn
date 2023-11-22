<?php

require __DIR__ . '/../vendor/autoload.php';
require 'config/main.php';
require 'helpers/helpers.php';

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event as DiscordEvent;
use Chorume\Database\Db;
use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\EventBet;
use Chorume\Repository\UserCoinHistory;

$db = new Db(
    getenv('DB_SERVER'),
    getenv('DB_DATABASE'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD')
);

$config = [
    'admin_role' => ['Moderador', 'Sub Moderador'],
    'images' => [
        'winners' => [
            'https://apito.me/imgs/money2.gif',
            'https://apito.me/imgs/money3.gif',
            'https://apito.me/imgs/money4.gif',
        ],
        'many_coins' => 'https://apito.me/imgs/coins.gif',
        'one_coin' => 'https://apito.me/imgs/coin.gif',
        'event' => 'https://apito.me/imgs/evento.gif',
        'nomoney' => 'https://apito.me/imgs/nomoney.gif',
        'events' => [
            'UFC' => 'https://apito.me/imgs/ufc.gif',
            'GENERIC' => 'https://apito.me/imgs/upcomingevents.gif',
            'LIBERTADORES' => 'https://apito.me/imgs/libertadores.gif',
        ],
        'place_bet' => 'https://apito.me/imgs/placebet.gif',
        'top_betters' => 'https://apito.me/imgs/tom.gif',
    ]
];

$userRepository = new User($db);
$eventRepository = new Event($db);
$eventChoiceRepository = new EventChoice($db);
$eventBetsRepository = new EventBet($db);
$userCoinHistoryRepository = new UserCoinHistory($db);

$discord = new Discord([
    'token' => getenv('TOKEN'),
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS
]);

$discord->on('ready', function (Discord $discord) {
    echo "Bot is ready!", PHP_EOL;

    // $command = new Command($discord, [
    //     'name' => 'coins',
    //     'description' => 'Mostra saldo de coins',
    // ]);
    // $discord->application->commands->save($command);

    $command = new Command($discord, [
        'name' => 'evento',
        'description' => 'Gerencia eventos para apostas',
        'options' => [
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'criar',
                'description' => 'Cria evento',
                'options' => [
                    [
                        'type' => Option::STRING,
                        'name' => 'nome',
                        'description' => 'Nome do evento',
                        'required' => true,
                    ],
                    [
                        'type' => Option::STRING,
                        'name' => 'a',
                        'description' => 'Opção A',
                        'required' => true,
                    ],
                    [
                        'type' => Option::STRING,
                        'name' => 'b',
                        'description' => 'Opção B',
                        'required' => true,
                    ],
                ]
            ],
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'iniciar',
                'description' => 'Inicia evento',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'id',
                        'description' => 'ID do evento',
                        'required' => true,
                    ],
                ]
            ],
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'fechar',
                'description' => 'Fecha evento e não recebe mais apostas',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'id',
                        'description' => 'ID do evento',
                        'required' => true,
                    ],
                ]
            ],
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'encerrar',
                'description' => 'Encerra evento e paga as apostas',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'id',
                        'description' => 'ID do evento',
                        'required' => true,
                    ],
                    [
                        'type' => Option::STRING,
                        'name' => 'opcao',
                        'description' => 'Opção A ou B.',
                        'required' => true,
                        'choices' => [
                            [
                                'name' => 'A',
                                'value' => 'A'
                            ],
                            [
                                'name' => 'B',
                                'value' => 'B'
                            ]
                        ]
                    ],
                ]
            ],
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'anunciar',
                'description' => 'Anuncia o evento de forma personalizada',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'id',
                        'description' => 'ID do evento',
                        'required' => true,
                    ],
                    [
                        'type' => Option::STRING,
                        'name' => 'banner',
                        'description' => 'Imagem do banner para utilizar ',
                        'required' => true,
                        'choices' => [
                            [
                                'name' => 'UFC',
                                'value' => 'UFC'
                            ],
                            [
                                'name' => 'Genérica',
                                'value' => 'GENERIC'
                            ],
                            [
                                'name' => 'Libertadores',
                                'value' => 'LIBERTADORES'
                            ]
                        ]
                    ],
                ]
            ],
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'listar',
                'description' => 'Lista eventos criados e pendentes para iniciar',
            ]
        ]
    ]);
    $discord->application->commands->save($command);

    // $command = new Command($discord, [
    //     'name' => 'aposta',
    //     'description' => 'Gerencia apostas de eventos',
    //     'options' => [
    //         [
    //             'type' => Option::SUB_COMMAND,
    //             'name' => 'entrar',
    //             'description' => 'Aposta em um evento',
    //             'options' => [
    //                 [
    //                     'type' => Option::INTEGER,
    //                     'name' => 'evento',
    //                     'description' => 'Número do evento',
    //                     'required' => true,
    //                 ],
    //                 [
    //                     'type' => Option::STRING,
    //                     'name' => 'opcao',
    //                     'description' => 'Opção A ou B.',
    //                     'required' => true,
    //                     'choices' => [
    //                         [
    //                             'name' => 'A',
    //                             'value' => 'A'
    //                         ],
    //                         [
    //                             'name' => 'B',
    //                             'value' => 'B'
    //                         ]
    //                     ]
    //                 ],
    //                 [
    //                     'type' => Option::NUMBER,
    //                     'name' => 'coins',
    //                     'description' => 'Quantidade de coins para apostar',
    //                     'required' => true,
    //                 ],
    //             ]
    //             ],
    //         [
    //             'type' => Option::SUB_COMMAND,
    //             'name' => 'listar',
    //             'description' => 'Lista minhas apostas mais recentes',
    //         ],
    //         [
    //             'type' => Option::SUB_COMMAND,
    //             'name' => 'listar',
    //             'description' => 'Lista minhas apostas mais recentes',
    //         ]
    //     ]
    // ]);
    // $discord->application->commands->save($command);

    // $command = new Command($discord, [
    //     'name' => 'top',
    //     'description' => 'Lista de TOPs',
    //     'options' => [
    //         [
    //             'type' => Option::SUB_COMMAND,
    //             'name' => 'apostadores',
    //             'description' => 'Lista minhas apostas mais recentes',
    //         ],
    //     ]
    // ]);
    // $discord->application->commands->save($command);

    // $command = new Command($discord, [
    //     'name' => 'test',
    //     'description' => 'Comando sandbox'
    // ]);
    // $discord->application->commands->save($command);

    // Listen for messages.
    // $discord->on(DiscordEvent::USER_UPDATE, function (Message $message, Discord $discord) {
    //     echo "{$message->author->username}: {$message->content}", PHP_EOL;
    // });

    // $discord->on(DiscordEvent::VOICE_STATE_UPDATE, function (VoiceStateUpdate $state, Discord $discord, $oldstate) {
        
    // });
});

$discord->listenCommand('test', function (Interaction $interaction) use ($discord, $config)  {
    if (!find_role_array($config['admin_role'], 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }
    // $guild = $discord->guilds->get('id', getenv('GUILD_ID'));

    // $guild->members->fetch($interaction->member->user->id)->done(function (Member $member) use ($interaction) {
    //     var_dump($member->getPermissions());
    //     $interaction->respondWithMessage(MessageBuilder::new()->setContent('Teste'));
    // });

    // var_dump($interaction);
    // return;

    /*
    *   SEND MESSAGE TO A CHANNEL
    */

    // $guild = $discord->guilds->get('id', getenv('GUILD_ID'));
    // $channel = $guild->channels->get('id', $interaction->channel->id);

    // /**
    //  * @var Embed $embed
    //  */
    // $embed = $discord->factory(Embed::class);
    // $embed->setTitle('Hello')
    //     ->setDescription('My name is DiscordPHP');
    // $channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
    // return true;
});

$discord->listenCommand('coins', function (Interaction $interaction) use ($discord, $config, $userRepository) {
    $discordId = $interaction->member->user->id;
    $user = $userRepository->getByDiscordId($discordId);

    if (!$discordId) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Aconteceu um erro com seu usuário, encha o saco do admin do bot!'), true);
        return;
    }

    if (empty($user)) {
        if ($userRepository->giveInitialCoins(
            $interaction->member->user->id,
            $interaction->member->user->username
        )) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent(sprintf(
                'Você acabou de receber suas **%s** coins iniciais! Aposte com sabedoria :man_mage:',
                100
            )), true);
        }
    }

    $coinsQuery = $userRepository->getCurrentCoins($interaction->member->user->id);
    $currentCoins = $coinsQuery[0]['total'];

    $receivedDaily = false;
    $dailyCoins = 100;
    if(!$userRepository->receivedDailyCoins($interaction->member->user->id) && !empty($user)) {
        $receivedDaily = true;
        $userRepository->giveDailyCoins($interaction->member->user->id, $dailyCoins);
    }

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle('EXTRATO DE COINS')
        ->setColor('#F5D920');

    if ($currentCoins <= 0) {
        $message = sprintf('Você não possui nenhuma coin, seu liso! :money_with_wings:', $currentCoins);
        $image = $config['images']['nomoney'];
    } else if ($currentCoins > 1000) {
        $message = sprintf('Você possui **%s** coins! !Tá faturando hein! :moneybag: :partying_face:', $currentCoins);
        $image = $config['images']['many_coins'];
    } else {
        $message = sprintf('Você possui **%s** coins! :coin:', $currentCoins);
        $image = $config['images']['one_coin'];
    }

    if ($receivedDaily) {
        $message .= "\n\nVocê recebeu suas **%s** coins diárias! :money_mouth:";
        $message = sprintf($message, $dailyCoins);
    }

    echo $ip = $_SERVER['REMOTE_ADDR'];
    echo $browser = $_SERVER['HTTP_USER_AGENT'];

    $embed
        ->setDescription($message)
        ->setImage($image);

    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), true);
});

$discord->listenCommand(['top', 'apostadores'], function (Interaction $interaction) use ($discord, $config, $userCoinHistoryRepository) {
    $top10list = $userCoinHistoryRepository->listTop10();
    $topBettersImage = $config['images']['top_betters'];

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle(sprintf('TOP 10 APOSTADORES'))
        ->setColor('#F5D920')
        ->setDescription('')
        ->setImage($topBettersImage);

    $users = '';
    $acc = '';

    foreach ($top10list as $bet) {
        $users .= sprintf("<@%s> \n", $bet['discord_user_id']);
        $acc .= sprintf("%s \n", $bet['total_coins']);
    }

    $embed
        ->addField([ 'name' => 'Usuário', 'value' => $users, 'inline' => 'true' ])
        ->addField([ 'name' => 'Acumulado', 'value' => $acc, 'inline' => 'true' ]);

    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed));
});

$discord->listenCommand(['aposta', 'listar'], function (Interaction $interaction) use ($discord, $config, $eventRepository, $eventBetsRepository, $userRepository)  {
});

$discord->listenCommand(['aposta', 'entrar'], function (Interaction $interaction) use ($discord, $config, $eventRepository, $eventBetsRepository, $userRepository)  {
    $discordId = $interaction->member->user->id;
    $eventId = $interaction->data->options['entrar']->options['evento']->value;
    $choiceKey = $interaction->data->options['entrar']->options['opcao']->value;
    $coins = $interaction->data->options['entrar']->options['coins']->value;
    $event = $eventRepository->listEventById($eventId);

    if (!$discordId) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Aconteceu um erro com seu usuário, encha o saco do admin do bot!'), true);
        return;
    }

    if (!$userRepository->userExistByDiscordId($discordId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você ainda não coleteu suas coins iniciais! Digita **/coins** e pegue suas coins! :coin::coin::coin: '), true);
        return;
    }

    if (empty($event)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent(sprintf('O evento #% não existe! :crying_cat_face:', $eventId)), true);
        return;
    }

    if ($eventRepository->isClosed($eventId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento fechado para apostas! :crying_cat_face: '), true);
        return;
    }

    if ($eventBetsRepository->alreadyBetted($discordId, $eventId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você já apostou neste evento!'), true);
        return;
    }

    if ($coins <= 0) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Valor da aposta inválido'), true);
        return;
    }

    if (!$userRepository->hasAvailableCoins($discordId, $coins)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não possui coins suficientes! :crying_cat_face:'), true);
        return;
    }

    if ($eventBetsRepository->create($discordId, $eventId, $choiceKey, $coins)) {
        /**
         * @var Embed $embed
         */
        $embed = $discord->factory(Embed::class);
        $embed
            ->setTitle(sprintf('%s #%s', $event[0]['event_name'], $event[0]['event_id']))
            ->setColor('#F5D920')
            ->setDescription(sprintf(
                "Você apostou **%s** chorume coins na **opção %s**.\n\nBoa sorte manolo!",
                $coins,
                $choiceKey
            ))
            ->setImage($config['images']['place_bet']);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), true);
    }
});

$discord->listenCommand(['evento', 'criar'], function (Interaction $interaction) use ($config, $eventRepository)  {
    if (!find_role_array($config['admin_role'], 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }

    $eventName = $interaction->data->options['criar']->options['nome']->value;
    $optionA = $interaction->data->options['criar']->options['a']->value;
    $optionB = $interaction->data->options['criar']->options['b']->value;

    if ($eventRepository->create(strtoupper($eventName), $optionA, $optionB)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento criado com sucesso!'), true);
    }
});

$discord->listenCommand(['evento', 'fechar'], function (Interaction $interaction) use ($config, $eventRepository)  {
    if (!find_role_array($config['admin_role'], 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }

    $eventId = $interaction->data->options['fechar']->options['id']->value;

    if ($eventRepository->closeEvent($eventId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento fechado! Esse evento não recebe mais apostas!'), false);
    }
});

$discord->listenCommand(['evento', 'encerrar'], function (Interaction $interaction) use ($discord, $config, $eventRepository, $eventChoiceRepository)  {
    if (!find_role_array($config['admin_role'], 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }

    $events = $eventRepository->listEventsClosed();

    if (empty($events)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não existem eventos para serem encerrados!'), true);
        return;
    }

    $eventId = $interaction->data->options['encerrar']->options['id']->value;
    $choiceKey = $interaction->data->options['encerrar']->options['opcao']->value;
    $eventItem = $eventRepository->getEventById($eventId);
    $choiceItem = $eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $choiceKey);
    $bets = $eventRepository->payoutEvent($eventId, $choiceKey);

    if (count($bets) === 0) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento encerrado não houveram apostas!'), true);
        return;
    }

    $eventsDescription = sprintf(
        "**Evento:** %s \n **Vencedor**: %s \n \n \n",
        $eventItem[0]['name'],
        $choiceItem[0]['description'],
    );

    $winnersImage = $config['images']['winners'][array_rand($config['images']['winners'])];

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
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

});

$discord->listenCommand(['evento','listar'], function (Interaction $interaction) use ($discord, $config, $eventRepository)  {
    $eventsOpen = $eventRepository->listEventsOpen();
    $eventsClosed = $eventRepository->listEventsClosed();
    $events = array_merge($eventsOpen, $eventsClosed);
    $ephemeralMsg = true;

    if (find_role_array($config['admin_role'], 'name', $interaction->member->roles)) {
        $ephemeralMsg = false;
    }

    $eventsDescription = "\n";

    if (empty($events)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não há eventos abertos!'), true);
    }

    foreach ($events as $event) {
        $eventOdds = $eventRepository->calculateOdds($event['event_id']);

        $eventsDescription .= sprintf(
            "**[#%s] %s** \n **Status: %s** \n **A**: %s \n **B**: %s \n \n",
            $event['event_id'],
            strtoupper($event['event_name']),
            $eventRepository::LABEL_LONG[(int) $event['event_status']],
            sprintf('%s (x%s)', $event['choices'][0]['choice_description'], number_format($eventOdds['oddsA'], 2)),
            sprintf('%s (x%s)', $event['choices'][1]['choice_description'], number_format($eventOdds['oddsB'], 2))
        );
    }

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle("EVENTOS")
        ->setColor('#F5D920')
        ->setDescription($eventsDescription)
        ->setImage($config['images']['event']);
    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), $ephemeralMsg);
});

$discord->listenCommand(['evento','anunciar'], function (Interaction $interaction) use ($discord, $config, $eventRepository)  {
    $eventId = $interaction->data->options['anunciar']->options['id']->value;
    $bannerKey = $interaction->data->options['anunciar']->options['banner']->value;

    $event = $eventRepository->listEventById($eventId);

    if (empty($event)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Esse evento não existe!'), true);
        return;
    }

    $eventOdds = $eventRepository->calculateOdds($eventId);
    $eventsDescription = sprintf(
        "**Status do Evento:** %s \n **A**: %s \n **B**: %s \n \n",
        $eventRepository::LABEL[$event[0]['event_status']],
        sprintf('%s (x%s)', $event[0]['choices'][0]['choice_description'], number_format($eventOdds['oddsA'], 2)),
        sprintf('%s (x%s)', $event[0]['choices'][1]['choice_description'], number_format($eventOdds['oddsB'], 2))
    );

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle(sprintf('[#%s] %s', $event[0]['event_id'], $event[0]['event_name']))
        ->setColor('#F5D920')
        ->setDescription($eventsDescription)
        ->setImage($config['images']['events'][$bannerKey]);
    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), false);
});

$discord->run();
