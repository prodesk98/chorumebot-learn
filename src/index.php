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
use Chorume\Application\Commands\Code\CodeCommand;
use Chorume\Application\Commands\Event\AdvertiseCommand;
use Chorume\Application\Commands\Event\BetCommand;
use Chorume\Application\Commands\Event\CloseCommand;
use Chorume\Application\Commands\Event\CreateCommand;
use Chorume\Application\Commands\Event\FinishCommand;
use Chorume\Application\Commands\Event\ListCommand;
use Chorume\Application\Commands\Generic\CoinsCommand;
use Chorume\Application\Commands\Generic\TopForbesCommand;
use Chorume\Application\Commands\Generic\TransferCommand;
use Chorume\Application\Commands\LittleAirplanes\FlyCommand;
use Chorume\Application\Commands\Master\AskCommand;
use Chorume\Application\Commands\Picasso\PaintCommand;
use Chorume\Application\Commands\Roulette\CloseCommand as RouletteCloseCommand;
use Chorume\Application\Commands\Roulette\CreateCommand as RouletteCreateCommand;
use Chorume\Application\Commands\Roulette\ExposeCommand as RouletteExposeCommand;
use Chorume\Application\Commands\Roulette\FinishCommand as RouletteFinishCommand;
use Chorume\Application\Commands\Roulette\ListCommand as RouletteListCommand;
use Chorume\Application\Commands\Test\TestCommand;
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

$db = Db::getInstance();

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
$coinsCommand = new CoinsCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$askCommand = new AskCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$paintCommand = new PaintCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$flyCommand = new FlyCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$topForbesCommand = new TopForbesCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$transferCommand = new TransferCommand($discord, $config, $redis, $userRepository, $userCoinHistoryRepository);
$eventAdvertiseCommand = new AdvertiseCommand($discord, $config, $eventRepository, $eventChoiceRepository);
$eventBetCommand = new BetCommand($discord, $config, $userRepository, $eventRepository, $eventBetsRepository);
$eventCreateCommand = new CreateCommand($discord, $config, $eventRepository);
$eventCloseCommand = new CloseCommand($discord, $config, $eventRepository, $eventChoiceRepository);
$eventFinishCommand = new FinishCommand($discord, $config, $eventRepository, $eventChoiceRepository);
$eventListCommand = new ListCommand($discord, $config, $eventRepository, $eventChoiceRepository);
$rouletteCreateCommand = new RouletteCreateCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository);
$rouletteListCommand = new RouletteListCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository);
$rouletteCloseCommand = new RouletteCloseCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository);
$rouletteFinishCommand = new RouletteFinishCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository);
$rouletteExposeCommand = new RouletteExposeCommand($discord, $config, $redis, $userRepository, $rouletteRepository, $rouletteBetRepository);


$discord->on('ready', function (Discord $discord) use ($talkRepository, $redis) {
    // Initialize application commands
    $initializeCommandsFiles = glob(__DIR__ . '/Application/Initialize/*Command.php');

    foreach ($initializeCommandsFiles as $initializeCommandsFile) {
        $initializeCommand = include $initializeCommandsFile;

        $command = new Command($discord, $initializeCommand);
        $discord->application->commands->save($command);
    }

    $botStartedAt = date('Y-m-d H:i:s');

    echo "  _______                           ___      __   " . PHP_EOL;
    echo " / ___/ / ___  ______ ____ _ ___   / _ )___ / /_  " . PHP_EOL;
    echo "/ /__/ _ / _ \/ __/ // /  ' / -_) / _  / _ / __/  " . PHP_EOL;
    echo "\___/_//_\___/_/  \_,_/_/_/_\__/ /____/\___\__/   " . PHP_EOL;
    echo "                                                  " . PHP_EOL;
    echo "                 Bot is ready!                    " . PHP_EOL;
    echo "         Started at: $botStartedAt                " . PHP_EOL;
});

$discord->on(DiscordEvent::MESSAGE_CREATE       , $messageCreateEvent);
$discord->listenCommand('test'                  , $testCommand);
$discord->listenCommand('codigo'                , $codeCommand);
$discord->listenCommand('coins'                 , $coinsCommand);
$discord->listenCommand('mestre'                , $askCommand);
$discord->listenCommand('picasso'               , $paintCommand);
$discord->listenCommand('avioeszinhos'          , $flyCommand);
$discord->listenCommand('transferir'            , $transferCommand);
$discord->listenCommand(['top', 'forbes']       , $topForbesCommand);
$discord->listenCommand(['evento', 'anunciar']  , $eventAdvertiseCommand);
$discord->listenCommand(['aposta', 'entrar']    , $eventBetCommand);
$discord->listenCommand(['evento', 'criar']     , $eventCreateCommand);
$discord->listenCommand(['evento', 'fechar']    , $eventCloseCommand);
$discord->listenCommand(['evento', 'encerrar']  , $eventFinishCommand);
$discord->listenCommand(['evento', 'listar']    , $eventListCommand);
$discord->listenCommand(['roleta', 'criar']     , $rouletteCreateCommand);
$discord->listenCommand(['roleta', 'listar']    , $rouletteListCommand);
$discord->listenCommand(['roleta', 'fechar']    , $rouletteCloseCommand);
$discord->listenCommand(['roleta', 'girar']     , $rouletteFinishCommand);
$discord->listenCommand(['roleta', 'apostar']   , $rouletteExposeCommand);
$discord->run();
