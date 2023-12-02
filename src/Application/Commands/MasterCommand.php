<?php

namespace Chorume\Application\Commands;

use Discord\Discord;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Embed\Embed;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Repository\User;
use Chorume\Repository\UserCoinHistory;
USE Chorume\Application\Discord\MessageComposer;

class MasterCommand
{
    private $discord;
    private $config;
    private $messageComposer;

    public function __construct(
        Discord $discord,
        $config
    )
    {
        $this->discord = $discord;
        $this->config = $config;
        $this->messageComposer = new MessageComposer($discord);
    }

    public function ask (Interaction $interaction)
    {
        $question = $interaction->data->options['pergunta']->value;

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

        $client = new HttpClient();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
        ];
        $body = [
            "model" => "gpt-3.5-turbo-instruct",
            "prompt" => $question,
            "max_tokens" => 100,
            "temperature" => 0.7
        ];
        $request = new Request('POST', 'https://api.openai.com/v1/completions', $headers, json_encode($body));
        $res = $client->send($request);
        $answer = json_decode($res->getBody());

        $message = "**Pergunta:** $question\n\n**Resposta:**";
        $message .= $answer->choices[0]->text;

        if ($answer->choices[0]->finish_reason === 'length') {
            $message .= '... etc e tals já tá bom né?!';
        }

        $interaction->respondWithMessage(
            $this->messageComposer->embed(
                'SABEDORIA DO MESTRE',
                $message,
                null,
                '#1D80C3'
            ),
        );
    }
}
