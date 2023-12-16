<?php

namespace Chorume\Application\Commands\Generic;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use Chorume\Application\Commands\Command;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;

class TopForbesCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private UserCoinHistory $userCoinHistoryRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $top10list = $this->userCoinHistoryRepository->listTop10();
        $topBettersImage = $this->config['images']['top_betters'];

        /**
         * @var Embed $embed
         */
        $embed = $this->discord->factory(Embed::class);
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
            ->addField(['name' => 'Usuário', 'value' => $users, 'inline' => 'true'])
            ->addField(['name' => 'Acumulado', 'value' => $acc, 'inline' => 'true']);

        $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed));
    }
}
