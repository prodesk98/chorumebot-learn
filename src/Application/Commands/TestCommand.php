<?php

namespace Chorume\Application\Commands;

use GuzzleHttp\Client;

use Predis\Client as RedisClient;
use Discord\Discord;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;

class TestCommand
{
    private Discord $discord;
    private $config;
    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;

    public function __construct(
        Discord $discord,
        $config,
        RedisClient $redis
    ) {
        $this->discord = $discord;
        $this->config = $config;
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function test()
    {
        $client = new HttpClient();

        $response = $client->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/SXhqBBsJYJNySHJXyoDs', [
            'json' => [
                'model_id' => 'eleven_multilingual_v2',
                'text' => 'Sabe o que eu fico puto? É que eu não consigo fazer nada direito. Eu tento fazer uma coisa, mas não consigo',
                'voice_settings' => [
                    'similarity_boost' => 1,
                    'stability' => 1,
                    'style' => 1,
                    'use_speaker_boost' => true,
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                "xi-api-key" => "726347e6bd18cd52026d03042a1ca5ae"
            ],
        ]);

        $body = $response->getBody();

        var_dump($body);

    }
}
