
<?php

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Discord\WebSockets\Event as DiscordEvent;

class DbSingleton
{
    private static $instance;

    private $db;

    private function __construct($server, $database, $user, $password)
    {
        // Lógica de inicialização do objeto Db
        $this->db = new Db($server, $database, $user, $password);
    }

    public static function getInstance($server, $database, $user, $password)
    {
        if (!self::$instance) {
            self::$instance = new self($server, $database, $user, $password);
        }

        return self::$instance->db;
    }
}
?>