<?php

namespace Chorume\Application\Commands\Roulette;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Voice\VoiceClient;
use Discord\Parts\Interactions\Interaction;
use Chorume\Application\Commands\Command;
use Chorume\Repository\Roulette;
use Chorume\Repository\RouletteBet;
use Discord\Parts\Embed\Embed;
use Chorume\Repository\User;
use Predis\Client as RedisClient;

class FinishCommand extends Command
{
    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Roulette $rouletteRepository,
        private RouletteBet $rouletteBetRepository
    ) {
    }

    public function handle(Interaction $interaction): void
    {
        $interaction->acknowledgeWithResponse(false)->then(function () use ($interaction) {
            $rouletteId = $interaction->data->options['girar']->options['id']->value;
            $roulette = $this->rouletteRepository->getRouletteById($rouletteId);

            if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                    true
                );
                return;
            }

            if (empty($roulette)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent('Roleta não existe!'),
                    true
                );
                return;
            }

            if (!$this->rouletteRepository->closeEvent($rouletteId)) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent(
                        sprintf('Ocorreu um erro ao finalizar Roleta **#%s**', $rouletteId)
                    )
                );
                return;
            }

            $roulette = $this->rouletteRepository->getRouletteById($rouletteId);
            $status = (int) $roulette[0]['status'];

            if ($status !== $this->rouletteRepository::CLOSED) {
                $message = sprintf('Roleta **#%s** precisa estar fechada para ser Girada!', $rouletteId);

                if ($status === $this->rouletteRepository::PAID) {
                    $message = sprintf('Roleta **#%s** já foi finalizada!', $rouletteId);
                }

                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent($message),
                    true
                );
                return;
            }

            $imageRouletteSpin = $this->config['images']['roulette']['spin'];

            $embedLoop = new Embed($this->discord);
            $embedLoop->setImage($imageRouletteSpin);
            $embedLoop->setTitle(sprintf('ROLETA #%s ENCERRADA', $rouletteId));
            $embedLoop->setDescription("**Sorteando um número!**");

            $builderLoop = new MessageBuilder();
            $builderLoop->addEmbed($embedLoop);
            $interaction->updateOriginalResponse($builderLoop, false);

            // Roulette Spinning Sound
            $channel = $this->discord->getChannel($interaction->channel_id);
            $audio = __DIR__ . '/../../../Audio/roulette.mp3';

            $voice = $this->discord->getVoiceClient($channel->guild_id);

            if ($voice) {
                $this->discord->getLogger()->info('Voice client already exists, playing audio...');
                $voice
                    ->playFile($audio)
                    ->done(function () use ($voice) {
                        $voice->close();
                    });
                return;
            }

            $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($audio, $interaction) {
                $this->discord->getLogger()->info('Voice client already exists, playing audio...');
                $voice
                    ->playFile($audio)
                    ->done(function () use ($voice) {
                        $voice->close();
                    });
            });

            $loop = $this->discord->getLoop();
            $loop->addTimer(8, function () use ($interaction, $roulette) {
                $rouletteId = $roulette[0]['id'];
                $winnerNumber = rand(0, 14);
                $winnerResult = null;
                $choice = null;

                if ($winnerNumber == 0) {
                    $winnerResult = Roulette::GREEN;
                    $choice = "🟩 G[$winnerNumber]";
                } elseif ($winnerNumber % 2 == 0) {
                    $winnerResult = Roulette::BLACK;
                    $choice = "⬛ BL[$winnerNumber]";
                } else {
                    $winnerResult = Roulette::RED;
                    $choice = "🟥 R[$winnerNumber]";
                }

                $bets = $this->rouletteRepository->payoutRoulette($roulette[0]['id'], $winnerResult);
                $this->redis->del("roulette:{$rouletteId}");

                $roulettesDescription = sprintf(
                    "**Evento:** %s \n **Vencedor**: %s \n \n \n",
                    $roulette[0]['description'],
                    "{$choice}",
                );

                $winnersImage = $this->config['images']['winners'][array_rand($this->config['images']['winners'])];

                /**
                 * @var Embed $embed
                 */
                $embed = $this->discord->factory(Embed::class);
                $embed
                    ->setTitle(sprintf("ROLETA ENCERRADA 💰\n[%s] %s", $rouletteId, $roulette[0]['description']))
                    ->setColor('#F5D920')
                    ->setDescription($roulettesDescription)
                    ->setImage($winnersImage);

                $earningsByUser = [];

                foreach ($bets as $bet) {
                    if ($bet['choice_key'] == $winnerResult) {
                        if (!isset($earningsByUser[$bet['discord_user_id']])) {
                            $earningsByUser[$bet['discord_user_id']] = 0;
                        }

                        $earningsByUser[$bet['discord_user_id']] += intval($bet['earnings']);
                    }
                }

                $awarded = '';
                $amount = '';

                foreach ($earningsByUser as $userId => $earnings) {
                    $awarded .= sprintf("<@%s>\n", $userId);
                    $amount .= sprintf("🪙 %s\n", $earnings);
                }

                $embed
                    ->addField(['name' => 'Premiação', 'value' => $awarded, 'inline' => 'true'])
                    ->addField(['name' => 'Valor (C$)', 'value' => $amount, 'inline' => 'true']);

                if (count($bets) === 0) {
                    $embednovo = new Embed($this->discord);
                    $embednovo
                        ->setTitle(sprintf('ROLETA #%s ENCERRADA', $rouletteId))
                        ->setColor('#F5D920')
                        ->setDescription("**Resultado**: Não houveram vencedores.");
                    $embed = $embednovo;
                }

                $descriptions = $this->config['images']['roulette']['numbers'];
                $embed->setImage($descriptions[$winnerNumber]);

                $builder = new MessageBuilder();
                $builder->addEmbed($embed);
                $interaction->updateOriginalResponse($builder, false);
            });
        });
    }
}
