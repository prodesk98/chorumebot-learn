<?php

namespace Chorume\Application\Commands\Learn;

use Discord\Builders\MessageBuilder;
use GuzzleHttp\Exception\GuzzleException;
use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Helpers\RedisHelper;

class LearnCommand extends Command
{

    private RedisHelper $redisHelper;
    private MessageComposer $messageComposer;
    private int $cooldownSeconds;
    private int $cooldownTimes;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private $userRepository,
        private $userCoinHistoryRepository
    ) {
        $this->redisHelper = new RedisHelper($redis);
        $this->messageComposer = new MessageComposer($this->discord);
        $this->cooldownSeconds = getenv('COMMAND_COOLDOWN_TIMER');
        $this->cooldownTimes = getenv('COMMAND_COOLDOWN_LIMIT');
    }

    public function handle(Interaction $interaction): void
    {
        $content = $interaction->data->options['conteúdo']->value;
        $contentLimit = getenv("LEARN_CONTENT_LIMIT", 4000);
        $learnCost = getenv("LEARN_COINS_COST", 20);

        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $this->discord->getLogger()->info(sprintf(
                'Learn command not allowed for user #%s (%s - %s)',
                $interaction->member->user->id,
                $interaction->member->user->username,
                $interaction->member->user->global_name
            ));

            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'),
                true
            );

            return;
        }

        if (
            !$this->redisHelper->cooldown(
                'cooldown:learn:content:' . $interaction->member->user->id,
                $this->cooldownSeconds,
                $this->cooldownTimes
            )
        ) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MEU CÉREBRO ESTÁ FRITANDOOO...🥵🥵',
                    'Calma ai senior, eu ainda estou aprendendo.',
                    $this->config['images']['gonna_press']
                ),
                true
            );
            return;
        }

        if (!$this->userCoinHistoryRepository->hasAvailableCoins($interaction->member->user->id, $learnCost)) {
            $message = sprintf(
                "Tu não tem dinheiro pra pagar meu ensino, vai trabalhar!\n\npreciso de **%s coins** para aprender isso!",
                $learnCost
            );

            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'EU TAMBÉM PAGO BOLETOS',
                    $message,
                    $this->config['images']['nomoney']
                ),
                true
            );
            return;
        }

        if (strlen($content) > $contentLimit) {
            $interaction->respondWithMessage(
                $this->messageComposer->embed(
                    'MUITA COISA! EU FAÇO SENAI, NÃO HARVARD.',
                    'Tu é escritor por acaso? Escreve menos na moralzinha!',
                    $this->config['images']['typer']
                ),
                true
            );
            return;
        }

        $interaction->acknowledgeWithResponse(true)->then(function () use ($interaction, $content, $learnCost) {
            $UpsertResult = $this->upsert($content, $interaction->member->user->global_name);

            if (!$UpsertResult->success){
                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        'NÃO ENTENDI O SEU ENSINO',
                        "circuitos fritando, memoria em colapso, estou explodindo...",
                        $this->config['images']['gonna_press']
                    )
                );
                return;
            }

            // retorno
            $message = sprintf("🧠Estou aprendendo rápido...\n\n**Palavras:** %s\n**Custo:** %s coins",
                sizeof(preg_split("/\s+/", $content)), $learnCost);

            $interaction->updateOriginalResponse(
                $this->messageComposer->embed(
                    'O SENAI ESTÁ RENDENDO',
                    $message,
                    $this->config['images']['thinking'],
                    '#1D80C3'
                )
            );

            // registra o débito
            $user = $this->userRepository->getByDiscordId($interaction->member->user->id);
            $this->userCoinHistoryRepository->create($user[0]['id'], -$learnCost, 'Learn');
        });
    }

    private function upsert(string $content, string $username): object
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('LEARN_TOKEN'),
        ];

        $body = [
            "content" => $content,
            "username" => $username,
        ];

        try {
            $request = new Request('POST', sprintf("%s/upsert", getenv("LEARN_ENDPOINT")), $headers, json_encode($body));
            $response = $client->send($request);
            return json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
            return (object)["success" => false];
        }
    }
}