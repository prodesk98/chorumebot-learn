<?php

require __DIR__ . '/../vendor/autoload.php';
require 'config/main.php';
require 'helpers/helpers.php';

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event as DiscordEvent;
use Chorume\Database\Db;
use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\EventBet;
use Chorume\Repository\Talk;
use Chorume\Repository\UserCoinHistory;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Chorume\Application\Services\GenericCommandService;
use Chorume\Application\Services\BetsService;
use Chorume\Application\Services\EventsService;
use Chorume\Application\Services\RouletteService;
use Chorume\Application\Services\RouletteBetService;

$db = new Db(
    getenv('DB_SERVER'),
    getenv('DB_DATABASE'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD')
);

$redis = new RedisClient([
    'scheme' => 'tcp',
    'host' => getenv('REDIS_HOST'),
    'password' => getenv('REDIS_PASSWORD'),
    'port' => 6379,
]);

$config = [
    'admin_role' => ['Admin', 'Gerente', 'Moderador', 'Sub Moderador', 'Bot Manager'],
    'images' => [
        'winners' => [
            'https://chorume.tech/imgs/money2.gif',
            'https://chorume.tech/imgs/money3.gif',
            'https://chorume.tech/imgs/money4.gif',
        ],
        'many_coins' => 'https://chorume.tech/imgs/coins.gif',
        'one_coin' => 'https://chorume.tech/imgs/coin.gif',
        'event' => 'https://chorume.tech/imgs/evento.gif',
        'nomoney' => 'https://chorume.tech/imgs/nomoney.gif',
        'sefodeu' => 'https://chorume.tech/imgs/sefodeu.gif',
        'events' => [
            'UFC' => 'https://chorume.tech/imgs/ufc.gif',
            'GENERIC' => 'https://chorume.tech/imgs/upcomingevents.gif',
            'LIBERTADORES' => 'https://chorume.tech/imgs/libertadores.gif',
        ],
        'place_bet' => 'https://chorume.tech/imgs/placebet.gif',
        'top_betters' => 'https://chorume.tech/imgs/tom.gif',
    ]
];

$userRepository = new User($db);
$eventRepository = new Event($db);
$eventChoiceRepository = new EventChoice($db);
$eventBetsRepository = new EventBet($db);
$userCoinHistoryRepository = new UserCoinHistory($db);
$rouletteRepository = new Roulette($db);
$rouletteBetRepository = new RouletteBet($db);
$talkRepository = new Talk($db);

$discord = new Discord([
    'token' => getenv('TOKEN'),
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS
]);

$myGenericCommandService = new GenericCommandService($discord, $config, $userRepository, $userCoinHistoryRepository);
$myBetsService = new BetsService($discord, $config, $userRepository, $eventRepository, $eventBetsRepository);
$myEventsService = new EventsService($discord, $config, $eventChoiceRepository, $eventRepository);
$myRouletteService = new RouletteService($discord, $config, $rouletteRepository, $rouletteBetRepository);

$discord->on('ready', function (Discord $discord) use ($talkRepository, $redis) {
    echo "Bot is ready!", PHP_EOL;

    // Listen for messages.
    $discord->on(DiscordEvent::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($talkRepository, $redis) {
        if ($redis->get('talks')) {
            $textTriggers = json_decode($redis->get('talks'), true);
        } else {
            $textTriggers = $talkRepository->listAllTriggers();
            $redis->set('talks', json_encode($textTriggers), 'EX', 60);
        }

        if ($found = find_in_array($message->content, 'triggertext', $textTriggers)) {
            $talk = $talkRepository->findById($found['id']);

            if (empty($talk)) {
                return;
            }

            $talkMessage = json_decode($talk[0]['answer']);

            switch ($talk[0]['type']) {
                case 'media':
                    $embed = $discord->factory(Embed::class);
                    $embed
                        ->setTitle($talkMessage->text)
                        ->setImage($talkMessage->image);
                    $message->channel->sendMessage(MessageBuilder::new()->addEmbed($embed));
                    break;
                default:
                    $message->channel->sendMessage(MessageBuilder::new()->setContent($talkMessage->text));
                    break;
            }
        }
    });

    $command = new Command($discord, [
        'name' => 'coins',
        'description' => 'Mostra saldo de coins',
    ]);
    $discord->application->commands->save($command);

    $command = new Command($discord, [
        'name' => 'transferir',
        'description' => 'Transfere coins para outro usuário',
        'options' => [
                [
                    'type' => Option::USER,
                    'name' => 'usuario',
                    'description' => 'Nome do usuário',
                    'required' => true,
                ],
                [
                    'type' => Option::NUMBER,
                    'name' => 'coins',
                    'description' => 'Quantidade de coins para transferir',
                    'required' => true,
                ],
            ]
    ]);
    $discord->application->commands->save($command);

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

    $command = new Command($discord, [
        'name' => 'aposta',
        'description' => 'Gerencia apostas de eventos',
        'options' => [
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'entrar',
                'description' => 'Aposta em um evento',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'evento',
                        'description' => 'Número do evento',
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
                    [
                        'type' => Option::NUMBER,
                        'name' => 'coins',
                        'description' => 'Quantidade de coins para apostar',
                        'required' => true,
                    ],
                ]
                ],
            // [
            //     'type' => Option::SUB_COMMAND,
            //     'name' => 'listar',
            //     'description' => 'Lista minhas apostas mais recentes',
            // ],
            // [
            //     'type' => Option::SUB_COMMAND,
            //     'name' => 'listar',
            //     'description' => 'Lista minhas apostas mais recentes',
            // ]
        ]
    ]);
    $discord->application->commands->save($command);

    $command = new Command($discord, [
        'name' => 'top',
        'description' => 'Lista de TOPs',
        'options' => [
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'apostadores',
                'description' => 'Lista minhas apostas mais recentes',
            ],
        ]
    ]);
    $discord->application->commands->save($command);

    $command = new Command($discord, [
        'name' => 'test',
        'description' => 'Comando sandbox'
    ]);
    $discord->application->commands->save($command);


    $command = new Command($discord, [
        'name' => 'roleta',
        'description' => 'Gerencia Roletas para apostas',
        'options' => [
            [
                'type' => Option::SUB_COMMAND,
                'name' => 'criar',
                'description' => 'Cria Roletas',
                'options' => [
                    [
                        'type' => Option::STRING,
                        'name' => 'nome',
                        'description' => 'Nome da Roleta',
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
                'description' => 'Fecha Roleta e não recebe mais apostas',
                'options' => [
                    [
                        'type' => Option::INTEGER,
                        'name' => 'id',
                        'description' => 'ID da Roleta',
                        'required' => true,
                    ],
                ]
            ],

            [
                'type' => Option::SUB_COMMAND,
                'name' => 'listar',
                'description' => 'Lista Roletas criados e pendentes para iniciar',
            ]
        ]
    ]);
    $discord->application->commands->save($command);
});

$discord->listenCommand('coins', [$myGenericCommandService, 'coins']);
$discord->listenCommand(['top', 'apostadores'], [$myGenericCommandService, 'topBetters']);
$discord->listenCommand(['transferir'], [$myGenericCommandService, 'transfer']);
$discord->listenCommand(['aposta', 'entrar'], [$myBetsService, 'makeBet']);
$discord->listenCommand(['evento', 'criar'], [$myEventsService, 'create']);
$discord->listenCommand(['evento', 'fechar'], [$myEventsService, 'close']);
$discord->listenCommand(['evento', 'encerrar'], [$myEventsService, 'finish']);
$discord->listenCommand(['evento', 'listar'], [$myEventsService, 'list']);
$discord->listenCommand(['evento', 'anunciar'], [$myEventsService, 'advertise']);
$discord->listenCommand(['roleta', 'criar' ], [$myRouletteService, 'create']);

$discord->run();
