<?php

namespace Chorume\Application\Commands;

use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Application\Discord\MessageComposer;

class MasterCommand
{
    private $discord;
    private $config;
    private $messageComposer;

    public function __construct(
        Discord $discord,
        $config
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function ask(Interaction $interaction)
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

        $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $question) {
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

            $interaction->updateOriginalResponse($this->messageComposer->embed(
                'SABEDORIA DO MESTRE',
                $message,
                null,
                '#1D80C3'
            ));
        });
    }
}
