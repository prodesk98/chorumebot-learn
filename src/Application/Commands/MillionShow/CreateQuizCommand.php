<?php

namespace Chorume\Application\Commands\MillionShow;

use Chorume\Application\Commands\Command;
use Chorume\Application\Discord\MessageComposer;
use Chorume\Repository\Quiz;
use Discord\Builders\MessageBuilder;
use Predis\Client as RedisClient;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;

class CreateQuizCommand extends Command
{
    private QuizBuilder $quizBuilder;
    private MessageComposer $messageComposer;

    public function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private $userRepository,
        private $userCoinHistoryRepository,
        private Quiz $quizRepository,
    ) {
        $this->quizBuilder = new QuizBuilder(
            $discord,
            $config,
            $redis,
            $userRepository,
            $quizRepository
        );
        $this->messageComposer = new MessageComposer($this->discord);
    }

    public function handle(Interaction $interaction): void
    {
        if (!find_role_array($this->config['admin_role'], 'name', $interaction->member->roles)) {
            $interaction->respondWithMessage(MessageBuilder::new()->setContent('Você não tem permissão para usar este comando!'), true);
            return;
        }

        $theme = $interaction->data->options["criar"]->options["tema"]->value;
        $amount = $interaction->data->options["criar"]->options["valor"]->value;

        $this->createQuiz($interaction, $theme, $amount);
    }

    private function createQuiz(Interaction $interaction, string $theme, float $amount): void
    {
        $interaction->acknowledgeWithResponse()->then(function () use ($interaction, $theme, $amount) {
            $g = $this->generativeQuiz($theme, $amount);

            if (!$g->success){
                $interaction->updateOriginalResponse(
                    $this->messageComposer->embed(
                        title: 'NÃO CONSEGUI PENSAR EM ALGO',
                        message: "Não foi possível criar um quiz!",
                        image: $this->config['images']['gonna_press']
                    )
                );
                return;
            }

            $quizId = $this->quizRepository->createEvent($theme, $amount, $g->truth, $g->question, $g->alternatives, $g->voice_url);

            if (!$quizId) {
                $interaction->respondWithMessage(MessageBuilder::new()->setContent("Não foi possível criar um quiz!"), true);
                return;
            }

            $this->quizBuilder->build($interaction, $quizId);
        });
    }

    private function generativeQuiz(string $theme, float $amount): object
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . getenv('LEARN_TOKEN'),
        ];

        $body = [
            "theme" => $theme,
            "amount" => $amount
        ];

        try {
            $request = new Request('POST', sprintf("%s/million-show", getenv("LEARN_ENDPOINT")), $headers, json_encode($body));
            $response = $client->send($request);
            $responseBody = $response->getBody()->getContents();

            $this->discord->getLogger()->debug($responseBody);

            return json_decode($responseBody);
        } catch (\Exception $e) {
            $this->discord->getLogger()->error($e->getMessage());
            return (object)["success" => false];
        }

    }
}