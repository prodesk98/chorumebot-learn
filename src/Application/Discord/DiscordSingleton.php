
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

class DiscordSingleton {

    private static $instance;

    private function __construct() {
        // Configuração da instância do Discord
        $token = getenv('TOKEN');
        $intents = Intents::getDefaultIntents() | Intents::GUILD_MEMBERS;

        self::$instance = new Discord([
            'token' => $token,
            'intents' => $intents,
        ]);
    }

    public static function getInstance() {
        if (!self::$instance) {
            new self();
        }

        return self::$instance;
    }
}

?>