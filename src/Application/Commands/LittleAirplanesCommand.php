<?php

namespace Chorume\Application\Commands;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Discord\Builders\MessageBuilder;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Exception;

class LittleAirplanesCommand
{
    private Discord $discord;
    private $config;
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private $userRepository;
    private $userCoinHistoryRepository;
    private int $cooldownSeconds;
    private int $cooldownTimes;

    public function __construct(
        Discord $discord,
        $config,
        RedisClient $redis,
        $userRepository,
        $userCoinHistoryRepository
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->userRepository = $userRepository;
        $this->userCoinHistoryRepository = $userCoinHistoryRepository;
        $this->cooldownSeconds = getenv('COMMAND_COOLDOWN_SECONDS');
        $this->cooldownTimes = getenv('COMMAND_COOLDOWN_TIMES');
    }

    public function fly(Interaction $interaction)
    {
        try {
            if (!find_role_array($this->config['master_role'], 'name', $interaction->member->roles)) {
                $interaction->respondWithMessage(
                    MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                    true
                );
                return;
            }

            $extraValueProbability = 0.5;
            $extraValue200Probability = 0.1;
            $members = array_keys($this->discord->getChannel($interaction->channel_id)->members->toArray());

            if (empty($members)) {
                $interaction->respondWithMessage($this->messageComposer->embed(
                    'MAH ÔÔÊ!',
                    'Mas tem ninguém nesse canal, logo não tem como eu jogar meus aviõeszinhos.... ôêê!'
                ));
            }

            $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $members, $extraValueProbability, $extraValue200Probability) {
                $interaction->updateOriginalResponse($this->messageComposer->embed(
                    'MAH ÔÔÊ!',
                    'Olha só quero ver, quero ver quem vai pegar os aviõeszinhos... ôêê!',
                    $this->config['images']['airplanes']
                ));

                $loop = $this->discord->getLoop();
                $loop->addTimer(5, function () use ($members, $interaction, $extraValueProbability, $extraValue200Probability) {
                    $airplanes = [];

                    foreach ($members as $member) {
                        if (mt_rand(0, 99) < $extraValueProbability * 100) {
                            $extraValue = mt_rand(0, 99) < $extraValue200Probability * 100 ? 200 : mt_rand(50, 100);

                            $airplanes[] = [
                                'discord_user_id' => $member,
                                'amount' => $extraValue
                            ];

                            $user = $this->userRepository->getByDiscordId($member);
                            $this->userCoinHistoryRepository->create($user[0]['id'], $extraValue, 'Airplane');
                        }
                    }

                    if (empty($airplanes)) {
                        $interaction->updateOriginalResponse($this->messageComposer->embed(
                            'MAH ÔÔÊ!',
                            'Acho que o Roque esqueceu de fazer meus aviõeszinhos.... ôêê!',
                            $this->config['images']['silvio_thats_ok'],
                        ));
                        return;
                    }

                    /**
                     * @var Embed $embed
                     */
                    $embed = $this->discord->factory(Embed::class);
                    $embed
                        ->setTitle('MAH ÔÔÊ!')
                        ->setColor('#8fce00')
                        ->setDescription('Os aviõeszinhos voaram pelo auditório e caíram em cima de:')
                        ->setImage($this->config['images']['silvio_cheers']);

                    $airports = '';
                    $amount = '';

                    foreach ($airplanes as $airplane) {
                        $airports .= sprintf("<@%s> \n", $airplane['discord_user_id']);
                        $amount .= sprintf("%s \n", $airplane['amount']);
                    }

                    $embed
                        ->addField(['name' => 'Nome', 'value' => $airports, 'inline' => 'true'])
                        ->addField(['name' => 'Valor', 'value' => $amount, 'inline' => 'true']);

                    $interaction->updateOriginalResponse(MessageBuilder::new()->addEmbed($embed));
                });

                return;
            });
        } catch (Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}
