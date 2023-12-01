<?php
namespace Chorume\Application\Services;
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
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;

class RouletteBetService {
    public $discord;
    public $config;
    public Roulette $rouletteRepository;
    public RouletteBet $rouletteBetRepository;
    public function __construct(Discord $discord, $config, Roulette $rouletteRepository, RouletteBet $rouletteBetRepository) {
        $this->discord = $discord;
        $this->config = $config;
        $this->rouletteRepository = $rouletteRepository;
        $this->rouletteBetRepository = $rouletteBetRepository;
    }

    public function rouletteCriar(Interaction $interaction){
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }
    
        $eventName = $interaction->data->options['criar']->options['nome']->value;
     
    
        if ($this->rouletteRepository->createRouletteEvent(strtoupper($eventName))) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Roleta criado com sucesso!'), true);
        }
    }

 
}

?>