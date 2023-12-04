<?php

require __DIR__ . '/../vendor/autoload.php';
require 'Helpers/FindHelper.php';

use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use Monolog\Logger as Monolog;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Discord\Discord;
use Discord\Parts\Interactions\Command\Command;
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
use Chorume\Application\Commands\GenericCommand;
use Chorume\Application\Commands\BetsCommand;
use Chorume\Application\Commands\EventsCommand;
use Chorume\Application\Commands\RouletteCommand;
use Chorume\Application\Commands\MasterCommand;
use Chorume\Application\Commands\PicassoCommand;
use Chorume\Application\Commands\TestCommand;
use Chorume\Application\Commands\CodeCommand;
use Chorume\Application\Events\MessageCreate;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['TOKEN']);

// Initialize $config files
$config = [];
$configFiles = glob(__DIR__ . '/config/*.php');

foreach ($configFiles as $file) {
    $fileConfig = include $file;

    if (is_array($fileConfig)) {
        $config = array_merge_recursive($config, $fileConfig);
    }
}

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

$logger = new Monolog('ChorumeCoins');

if (getenv('ENVIRONMENT') === 'production') {
    $formatter = new JsonFormatter();
    $stream = new StreamHandler(__DIR__ . '/application-json.log', Level::fromName(getenv('LOG_LEVEL')));
    $stream->setFormatter($formatter);
    $logger->pushHandler($stream);
}

$logger->pushHandler(new StreamHandler('php://stdout', Level::fromName(getenv('LOG_LEVEL'))));

$discord = new Discord([
    'token' => getenv('TOKEN'),
    'logger' => $logger,
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
]);

$userRepository = new User($db);
$userCoinHistoryRepository = new UserCoinHistory($db);
$eventRepository = new Event($db);
$eventChoiceRepository = new EventChoice($db);
$eventBetsRepository = new EventBet($db);
$rouletteRepository = new Roulette($db);
$rouletteBetRepository = new RouletteBet($db);
$talkRepository = new Talk($db);

$messageCreateEvent = new MessageCreate($discord, $config, $redis, $talkRepository);
$testCommand = new TestCommand($discord, $config, $redis);
$codeCommand = new CodeCommand($discord, $config, $redis);
$masterCommand = new MasterCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$picassoCommand = new PicassoCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$genericCommand = new GenericCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$betsCommand = new BetsCommand($discord, $config, $userRepository, $eventRepository, $eventBetsRepository);
$eventsCommand = new EventsCommand($discord, $config, $eventChoiceRepository, $eventRepository);
$rouletteCommand = new RouletteCommand($discord, $config, $rouletteRepository, $rouletteBetRepository, $userRepository, $redis);

$discord->on('ready', function (Discord $discord) use ($talkRepository, $redis) {
    // Initialize application commands
    $initializeCommandsFiles = glob(__DIR__ . '/Application/Initialize/*Command.php');

    foreach ($initializeCommandsFiles as $initializeCommandsFile) {
        $initializeCommand = include $initializeCommandsFile;

        $command = new Command($discord, $initializeCommand);
        $discord->application->commands->save($command);
    }

    echo "  _______                           ___      __   " . PHP_EOL;
    echo " / ___/ / ___  ______ ____ _ ___   / _ )___ / /_  " . PHP_EOL;
    echo "/ /__/ _ / _ \/ __/ // /  ' / -_) / _  / _ / __/  " . PHP_EOL;
    echo "\___/_//_\___/_/  \_,_/_/_/_\__/ /____/\___\__/   " . PHP_EOL;
    echo "                                                  " . PHP_EOL;
    echo "                 Bot is ready!                    " . PHP_EOL;
});

$discord->on(DiscordEvent::MESSAGE_CREATE, [$messageCreateEvent, 'messageCreate']);
$discord->listenCommand('test', [$testCommand, 'test']);
$discord->listenCommand('codigo', [$codeCommand, 'code']);
$discord->listenCommand('coins', [$genericCommand, 'coins']);
$discord->listenCommand('mestre', [$masterCommand, 'ask']);
$discord->listenCommand('picasso', [$picassoCommand, 'paint']);
$discord->listenCommand(['top', 'apostadores'], [$genericCommand, 'topBetters']);
$discord->listenCommand(['transferir'], [$genericCommand, 'transfer']);
$discord->listenCommand(['aposta', 'entrar'], [$betsCommand, 'makeBet']);
$discord->listenCommand(['evento', 'criar'], [$eventsCommand, 'create']);
$discord->listenCommand(['evento', 'fechar'], [$eventsCommand, 'close']);
$discord->listenCommand(['evento', 'encerrar'], [$eventsCommand, 'finish']);
$discord->listenCommand(['evento', 'listar'], [$eventsCommand, 'list']);
$discord->listenCommand(['evento', 'anunciar'], [$eventsCommand, 'advertise']);
$discord->listenCommand(['roleta', 'criar'], [$rouletteCommand, 'create']);
$discord->listenCommand(['roleta', 'listar'], [$rouletteCommand, 'list']);
$discord->listenCommand(['roleta', 'fechar'], [$rouletteCommand, 'close']);
$discord->listenCommand(['roleta', 'girar'], [$rouletteCommand, 'finish']);
$discord->listenCommand(['roleta', 'apostar' ], [$rouletteCommand, 'expose']);
$discord->run();
