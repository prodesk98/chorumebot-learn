<?php

require __DIR__ . '/../vendor/autoload.php';
require 'config/main.php';

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
    //             'name' => 'criar',
    //             'description' => 'Aposta em um evento',
    //             'options' => [
    //                 [
    //                     'type' => Option::INTEGER,
    //                     'name' => 'evento_id',
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
    //                     'type' => Option::INTEGER,
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
    $discord->on(DiscordEvent::MESSAGE_CREATE, function (Message $message, Discord $discord) {
        echo "{$message->author->username}: {$message->content}", PHP_EOL;
    });
});

$discord->listenCommand('test', function (Interaction $interaction) use ($discord)  {
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

$discord->listenCommand(['aposta', 'criar'], function (Interaction $interaction) use ($eventRepository, $eventBetsRepository, $userRepository)  {
    $discordId = $interaction->member->user->id;
    $eventId = $interaction->data->options['criar']->options['evento_id']->value;
    $choiceKey = $interaction->data->options['criar']->options['opcao']->value;
    $coins = $interaction->data->options['criar']->options['coins']->value;

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
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não possui coins suficientes! :crying_cat_face: '), true);
        return;
    }

    if ($eventBetsRepository->create($discordId, $eventId, $choiceKey, $coins)) {
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(
                sprintf('Você apostou **%s** na **opção %s**. Boa sorte manolo!', $coins, $choiceKey),
                true
            )
        );
    }
});

$discord->listenCommand(['evento', 'criar'], function (Interaction $interaction) use ($eventRepository)  {
    $eventName = $interaction->data->options['criar']->options['nome']->value;
    $optionA = $interaction->data->options['criar']->options['a']->value;
    $optionB = $interaction->data->options['criar']->options['b']->value;

    if ($eventRepository->create($eventName, $optionA, $optionB)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento criado com sucesso!'), true);
    }
});

$discord->listenCommand(['evento', 'fechar'], function (Interaction $interaction) use ($eventRepository)  {
    $eventId = $interaction->data->options['fechar']->options['id']->value;

    if ($eventRepository->closeEvent($eventId)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento fechado com sucesso! Não é possível mais apostas neste evento!'), true);
    }
});

$discord->listenCommand(['evento', 'encerrar'], function (Interaction $interaction) use ($discord, $eventRepository, $eventChoiceRepository)  {
    $eventId = $interaction->data->options['encerrar']->options['id']->value;
    $eventItem = $eventRepository->getEventById($eventId);
    $choiceKey = $interaction->data->options['encerrar']->options['opcao']->value;
    $choiceItem = $eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $choiceKey);
    $bets = $eventRepository->payoutEvent($eventId, $choiceKey);

    if (count($bets) === 0) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Evento encerrado não houveram apostas!'), true);
    }

    $eventsDescription = sprintf(
        "**Evento:** %s \n **Vencedor**: %s \n \n \n",
        $eventItem[0]['name'],
        $choiceItem[0]['description'],
    );

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle(sprintf('EVENTO #%s ENCERRADO', $eventId))
        ->setColor('#F5D920')
        ->setDescription($eventsDescription);

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

$discord->listenCommand(['evento','listar'], function (Interaction $interaction) use ($discord, $eventRepository)  {
    $eventsOpen = $eventRepository->listEventsOpenClosed();
    $eventsDescription = "\n";

    if (empty($eventsOpen)) {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Não há eventos abertos!'), true);
    }

    foreach ($eventsOpen as $event) {
        $eventsDescription .= sprintf(
            "**%s** \n **Evento:** %s \n **A**: %s \n **B**: %s \n \n",
            $event['event_status'] == $eventRepository::CLOSED ? "{$event['event_name']} (Apostas Encerradas)" : $event['event_name'],
            $event['event_id'],
            $event['choices'][0]['choice_description'],
            $event['choices'][1]['choice_description']
        );
    }

    /**
     * @var Embed $embed
     */
    $embed = $discord->factory(Embed::class);
    $embed
        ->setTitle("EVENTOS ABERTOS")
        ->setColor('#F5D920')
        ->setDescription($eventsDescription);
    $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed));
});

$discord->listenCommand('coins', function (Interaction $interaction) use ($userRepository) {
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

    if ($currentCoins == 0) {
        $message = 'Você não possui nenhuma coin, seu liso! :money_with_wings:';
    } else if ($currentCoins > 1000) {
        $message = 'Você possui **%s** coins! Tá faturando hein! :moneybag: :partying_face:';
    } else {
        $message = 'Você possui **%s** coins! :coin:';
    }

    $interaction->respondWithMessage(MessageBuilder::new()->setContent(sprintf($message, $currentCoins)), true);
});

$discord->run();
