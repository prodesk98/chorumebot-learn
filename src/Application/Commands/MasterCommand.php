<?php

namespace Chorume\Application\Commands;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;

class MasterCommand
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

    public function ask(Interaction $interaction)
    {
        $question = $interaction->data->options['pergunta']->value;
        $askCost = getenv('MASTER_COINS_COST');

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $askCost)) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MESTRE NÃO É OTÁRIO',
                    sprintf("Tu não tem dinheiro pra pagar o mestre, vai trabalhar!\n\nO mestre cobra singelos %s coins por pergunta!", $askCost),
                    $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        if (
            !$this->redisHelper->cooldown(
                'cooldown:master:ask:' . $interaction->member->user->id,
                $this->cooldownSeconds,
                $this->cooldownTimes
            )
        ) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MESTRE ODEIA REPETIÇÕES',
                    'Muito mais devagar aí cnpjoto, calabreso! Aguarde 1 minuto para fazer outra pergunta!',
                    $this->config['images']['gonna_press']
                ),
                true
            );
            return;
        }

        if (strlen($question) > 100) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MESTRE FICOU PUTO',
                    'Tu é escritor por acaso? Escreve menos na moralzinha!',
                    $this->config['images']['typer']
                ),
                true
            );
            return;
        }

        $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $question, $askCost) {
            $client = new HttpClient();
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
            ];
            $body = [
                "model" => getenv('OPENAI_COMPLETION_MODEL'),
                "messages" => [
                    [
                        "role" => "user",
                        "content" => $question
                    ]
                ],
                "temperature" => 1,
                "top_p" => 1,
                "n" => 1,
                "stream" => false,
                "max_tokens" => 150,
                "presence_penalty" => 0,
                "frequency_penalty" => 0
            ];
            $request = new Request('POST', 'https://api.openai.com/v1/chat/completions', $headers, json_encode($body));
            $response = $client->send($request);
            $data = json_decode($response->getBody());

            $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
            $message .= $data->choices[0]->message->content;

            if ($data->choices[0]->finish_reason === 'length') {
                $message .= '... etc e tals já tá bom né?!';
            }

            $message .= sprintf("\n\n**Custo:** %s coins", $askCost);

            $interaction->updateOriginalResponse($this->messageComposer->embed(
                'SABEDORIA DO MESTRE',
                $message,
                null,
                '#1D80C3'
            ));

            $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
            $this->userCoinHistoryRepository->create($user[0]['id'], -$askCost, 'Master');
        });
    }
}
