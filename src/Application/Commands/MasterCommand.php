<?php

namespace Chorume\Application\Commands;

use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;
use Exception;

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
        $askCost = $originalAskCost = getenv('MASTER_COINS_COST');
        $boost = $interaction->data->options['boost'];
        $boostValue = 0;
        $boostMessage = '';
        $questionLimit = $boost ? getenv('MASTER_QUESTION_SIZE_LIMIT') * 20 : getenv('MASTER_QUESTION_SIZE_LIMIT');

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

        if ($boost) {
            $askCost += $boostValue = $boost->value;
            $boostMessage = sprintf("\n\nAlém do **Boost** de  **%s** coins", $boost->value);
        }

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $askCost)) {
            $message = sprintf(
                "Tu não tem dinheiro pra pagar o mestre, vai trabalhar!\n\nO mestre cobra singelos **%s coins** por pergunta!%s",
                $originalAskCost,
                $boostMessage
            );

            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MESTRE NÃO É OTÁRIO',
                    $message,
                    $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        if (strlen($question) > $questionLimit) {
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

        try {
            if ($boost) {
                $interaction->acknowledgeWithResponse(true)->then(function () use ($interaction, $question, $askCost, $questionLimit, $boostValue) {
                    $data = $this->requestQuestion($question, $questionLimit, true);

                    $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
                    $message .= $data->choices[0]->message->content;

                    if ($data->choices[0]->finish_reason === 'length') {
                        $message .= '... e bla bla bla.';
                    }

                    $message .= sprintf("\n\n**Custo:** %s coins", $askCost);

                    $interaction->user->sendMessage(
                        $this->messageComposer->embed(
                            'SABEDORIA DO MESTRE',
                            $message,
                            null,
                            '#1D80C3'
                        )
                    );

                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            'SABEDORIA DO MESTRE',
                            "Respostas com **boost** vão para DM. Cheque sua DM, e a resposta está lá!",
                            null,
                            '#1D80C3'
                        )
                    );

                    $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
                    $this->userCoinHistoryRepository->create($user[0]['id'], -$askCost, 'Master', null, "Boost: $boostValue");
                });

                return;
            }

            $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $question, $askCost, $boost) {
                $questionData = $this->requestQuestion($question, getenv('MASTER_DEFAULT_TOKENS_AMOUNT_RESPONSE'));

                $message = "**Pergunta:**\n$question\n\n**Resposta:**\n";
                $message .= $questionData->choices[0]->message->content;

                if ($questionData->choices[0]->finish_reason === 'length') {
                    $message .= '... etc e tals já tá bom né?!';
                }

                $message .= sprintf("\n\n**Custo:** %s coins", $askCost);

                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'SABEDORIA DO MESTRE',
                        $message,
                        null,
                        '#1D80C3'
                    )
                );

                if (getenv('MASTER_VOICE_ENABLED')) {
                    $audioFilename = $this->generateVoice($questionData->choices[0]->message->content);

                    $interaction->updateOriginalResponse(
                        $this->messageComposer->embed(
                            'SABEDORIA DO MESTRE',
                            $message,
                            null,
                            '#1D80C3',
                            $audioFilename
                        )
                    );
                }

                $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
                $this->userCoinHistoryRepository->create($user[0]['id'], -$askCost, 'Master');
            });

            return;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }

    public function generateVoice(string $text)
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            "xi-api-key" => getenv('ELEVENLABS_API_KEY'),
        ];

        $body = [
            'model_id' => 'eleven_multilingual_v2',
            'text' => $text,
            'voice_settings' => [
                'similarity_boost' => 1,
                'stability' => 1,
                'style' => 1,
                'use_speaker_boost' => true,
            ],
        ];

        try {
            $request = new Request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/SXhqBBsJYJNySHJXyoDs', $headers, json_encode($body));
            $response = $client->send($request);
            $data = $response->getBody()->getContents();
            $filename = sprintf("%s/../../../temp_audio/%s.mp3", __DIR__, date('d-m-Y_H-i-s-m-u'));

            file_put_contents($filename, $data);

            return $filename;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }

    private function requestQuestion(string $question, int $tokens, bool $boost = false)
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        ];

        $messages = [
            [
                "role" => "user",
                "content" => $question
            ]
        ];

        if (!$boost) {
            $messages[] = [
                "role" => "system",
                "content" => getenv('MASTER_HUMOR')
            ];
        }

        $body = [
            "model" => getenv('OPENAI_COMPLETION_MODEL'),
            "messages" => $messages,
            "temperature" => 1.2,
            "top_p" => 1,
            "n" => 1,
            "stream" => false,
            "max_tokens" => $tokens,
            "presence_penalty" => 0,
            "frequency_penalty" => 0
        ];

        try {
            $request = new Request('POST', 'https://api.openai.com/v1/chat/completions', $headers, json_encode($body));
            $response = $client->send($request);
            $data = json_decode($response->getBody()->getContents());
            return $data;
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
        }
    }
}
