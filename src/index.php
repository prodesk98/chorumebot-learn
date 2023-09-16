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

$db = new Db(
    getenv('DB_SERVER'),
    getenv('DB_DATABASE'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD')
);

$images = [
    'winners' => [
        'https://i.ibb.co/5LCJqzw/money2.gif',
        'https://i.ibb.co/3sst809/money3.gif',
        'https://i.ibb.co/wBKjF8G/money4.gif',
    ],
    'many_coins' => 'https://i.ibb.co/vDPHrmL/coins.gif',
    'one_coin' => 'https://i.ibb.co/yhYC00z/coin.gif',
    'event' => 'https://i.ibb.co/19tbY3M/evento.gif',
    'nomoney' => 'https://i.ibb.co/87xz70n/nomoney.gif',
    'events' => [
        'UFC' => 'https://i.ibb.co/ZKVGrBL/ufc.gif',
        'GENERIC' => 'https://i.ibb.co/phnN1RH/upcomingevents.gif',
    ],
    'place_bet' => 'https://i.ibb.co/zhY0SNb/placebet.gif',
];


$userRepository = new User($db);
$eventRepository = new Event($db);
$eventChoiceRepository = new EventChoice($db);
$eventBetsRepository = new EventBet($db);

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
    //         ]
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

    $discord->on(DiscordEvent::VOICE_STATE_UPDATE, function (VoiceStateUpdate $state, Discord $discord, $oldstate) {
        
    });
});

$discord->listenCommand('test', function (Interaction $interaction) use ($discord)  {
    if (!find_role('Moderator', 'name', $interaction->member->roles)) {
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

    /**
    * @var Embed $embed
    */
});

$discord->listenCommand('coins', function (Interaction $interaction) use ($discord, $images, $userRepository) {
    $discordId = $interaction->member->user->id;
    $user = $userRepository->getByDiscordId($discordId);

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

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle('EXTRATO DE COINS')
        ->setColor('#F5D920');

    if ($currentCoins <= 0) {
        $embed->setDescription(sprintf('Você não possui nenhuma coin, seu liso! :money_with_wings:', $currentCoins))
            ->setImage($images['nomoney']);
    } else if ($currentCoins > 1000) {
        $embed->setDescription(sprintf('Você possui **%s** coins! Tá faturando hein! :moneybag: :partying_face:', $currentCoins))
            ->setImage($images['many_coins']);
    } else {
        $embed->setDescription(sprintf('Você possui **%s** coins! :coin:', $currentCoins))
            ->setImage($images['one_coin']);
    }

    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), true);
});

$discord->listenCommand(['aposta', 'entrar'], function (Interaction $interaction) use ($discord, $images, $eventRepository, $eventBetsRepository, $userRepository)  {
    $discordId = $interaction->member->user->id;
    $eventId = $interaction->data->options['entrar']->options['evento']->value;
    $choiceKey = $interaction->data->options['entrar']->options['opcao']->value;
    $coins = $interaction->data->options['entrar']->options['coins']->value;
    $event = $eventRepository->listEventById($eventId);

    if (!$userRepository->userExistByDiscordId($discordId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você ainda não coleteu suas coins iniciais! Digita **/coins** e pegue suas coins! :coin::coin::coin: '), true);
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
            ->setImage($images['place_bet']);
        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), true);
    }
});

$discord->listenCommand(['evento', 'criar'], function (Interaction $interaction) use ($eventRepository)  {
    if (!find_role('Moderator', 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }

    $eventName = $interaction->data->options['criar']->options['nome']->value;
    $optionA = $interaction->data->options['criar']->options['a']->value;
    $optionB = $interaction->data->options['criar']->options['b']->value;

    if ($eventRepository->create($eventName, $optionA, $optionB)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento criado com sucesso!'), true);
    }
});

$discord->listenCommand(['evento', 'fechar'], function (Interaction $interaction) use ($eventRepository)  {
    if (!find_role('Moderator', 'name', $interaction->member->roles)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
        return;
    }

    $eventId = $interaction->data->options['fechar']->options['id']->value;

    if ($eventRepository->closeEvent($eventId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento fechado com sucesso! Não é possível mais apostas neste evento!'), true);
    }
});

$discord->listenCommand(['evento', 'encerrar'], function (Interaction $interaction) use ($discord, $images, $eventRepository, $eventChoiceRepository)  {
    if (!find_role('Moderator', 'name', $interaction->member->roles)) {
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

    $winnersImage = $images['winners'][array_rand($images['winners'])];

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

$discord->listenCommand(['evento','listar'], function (Interaction $interaction) use ($discord, $images, $eventRepository)  {
    $eventsOpen = $eventRepository->listEventsOpen();
    $eventsDescription = "\n";

    if (empty($eventsOpen)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não há eventos abertos!'), true);
    }

    foreach ($eventsOpen as $event) {
        $eventOdds = $eventRepository->calculateOdds($event['event_id']);

        $eventsDescription .= sprintf(
            "**%s** \n **Evento:** %s \n **A**: %s \n **B**: %s \n \n",
            $event['event_status'] == $eventRepository::CLOSED ? "{$event['event_name']} (Apostas Encerradas)" : $event['event_name'],
            $event['event_id'],
            sprintf('%s (x%s)', $event['choices'][0]['choice_description'], number_format($eventOdds['oddsA'], 2)),
            sprintf('%s (x%s)', $event['choices'][1]['choice_description'], number_format($eventOdds['oddsB'], 2))
        );
    }

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle("EVENTOS ABERTOS")
        ->setColor('#F5D920')
        ->setDescription($eventsDescription)
        ->setImage($images['event']);
    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), true);
});

$discord->listenCommand(['evento','anunciar'], function (Interaction $interaction) use ($discord, $images, $eventRepository)  {
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
        ->setTitle($event[0]['event_name'])
        ->setColor('#F5D920')
        ->setDescription($eventsDescription)
        ->setImage($images['events'][$bannerKey]);
    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed), false);
});

$discord->run();
