<?php

namespace Chorume\Application\Commands\MillionShow;

use Chorume\Repository\Quiz;
use Chorume\Repository\User;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Exceptions\FileNotFoundException;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Discord\Voice\VoiceClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Predis\Client as RedisClient;

class  QuizBuilder
{

    function __construct(
        private Discord $discord,
        private $config,
        private RedisClient $redis,
        private User $userRepository,
        private Quiz $quizRepository
    )
    {

    }

    public function build(Interaction $interaction, int $quizId): void
    {
        $quizBetRow = ActionRow::new();

        $quiz = $this->quizRepository->getQuizById($quizId);

        if (empty($quiz)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Quiz não existe!'),
                true
            );
            return;
        }

        $quiz = (object)$quiz[0];

        $amountBet = (int) $quiz->amount;
        $status = (int) $quiz->status;

        if ($status !== $this->quizRepository::OPEN) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent('Quiz precisa estar aberta para response!'),
                true
            );
            return;
        }

        $gameDataCache = $this->redis->get("quiz:{$quiz->id}");
        $gameData = unserialize($gameDataCache ?? '');

        if (!$gameData) {
            $gameData = new QuizGameData($quiz->id);
            $serelized = serialize($gameData);
            $this->redis->set("quiz:{$quiz->id}", $serelized);
        }

        $buttonA = Button::new(Button::STYLE_PRIMARY)
            ->setLabel("A")
            ->setListener(
                function (Interaction $interactionUser) use ($interaction, $quizId, $quiz, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->betQuiz(
                        $fromDiscordId,
                        Quiz::A,
                        $quizId,
                        $interaction,
                        $quiz,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonB = Button::new(Button::STYLE_PRIMARY)
            ->setLabel("B")
            ->setListener(
                function (Interaction $interactionUser) use ($interaction, $quizId, $quiz, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->betQuiz(
                        $fromDiscordId,
                        Quiz::B,
                        $quizId,
                        $interaction,
                        $quiz,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonC = Button::new(Button::STYLE_PRIMARY)
            ->setLabel("C")
            ->setListener(
                function (Interaction $interactionUser) use ($interaction, $quizId, $quiz, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->betQuiz(
                        $fromDiscordId,
                        Quiz::C,
                        $quizId,
                        $interaction,
                        $quiz,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $buttonD = Button::new(Button::STYLE_PRIMARY)
            ->setLabel("D")
            ->setListener(
                function (Interaction $interactionUser) use ($interaction, $quizId, $quiz, $amountBet, &$gameData) {
                    $fromDiscordId = $interactionUser->member->user->id;
                    $userDiscord = $interactionUser->member->user;

                    $this->betQuiz(
                        $fromDiscordId,
                        Quiz::D,
                        $quizId,
                        $interaction,
                        $quiz,
                        $amountBet,
                        $gameData,
                        $userDiscord,
                        $interactionUser
                    );
                },
                $this->discord
            );

        $quizBetRow->addComponent($buttonA);
        $quizBetRow->addComponent($buttonB);
        $quizBetRow->addComponent($buttonC);
        $quizBetRow->addComponent($buttonD);

        $embed = $this->buildEmbedForQuiz($quiz, $gameData);

        $builder = new MessageBuilder();
        $builder->addEmbed($embed);
        $builder->addComponent($quizBetRow);
        $interaction->updateOriginalResponse($builder);

        if ($quiz->voice_url !== null) {
            $channel = $this->discord->getChannel($interaction->channel_id);
            $voice = $this->discord->getVoiceClient($channel->guild_id);
            if ($channel->isVoiceBased()) {
                $audio = $this->voiceDownload($quiz->voice_url);
                if ($audio !== null) {
                    if ($voice) {
                        $this->discord->getLogger()->info('Voice client already exists, playing roulette spin audio...');
                        try {
                            $voice->playFile($audio);
                        } catch (FileNotFoundException $e) {
                            $this->discord->getLogger()->error($e);
                        }
                    } else {
                        $this->discord->joinVoiceChannel($channel)->done(function (VoiceClient $voice) use ($quiz, $interaction, $audio) {
                            $this->discord->getLogger()->info('Playing Little Airplanes audio...');
                            $voice->playFile($audio);
                        });
                    }
                }
            }
        }
    }

    private function buildEmbedForQuiz(
        object $quiz,
        QuizGameData &$gameData
    ): Embed
    {
        /*$playersA = array_filter($gameData->players, function ($player) {
            return $player->choice == Quiz::A;
        });

        $playersB = array_filter($gameData->players, function ($player) {
            return $player->choice == Quiz::B;
        });

        $playersC = array_filter($gameData->players, function ($player) {
            return $player->choice == Quiz::C;
        });

        $playersD = array_filter($gameData->players, function ($player) {
            return $player->choice == Quiz::D;
        });*/

        $embed = new Embed($this->discord);
        $embed->setTitle($quiz->question)
            ->setColor('#F2B90C')
            ->setDescription(sprintf('Quiz [#%s]', $quiz->id));

        $embed
            ->addField(['name' => 'Prêmio', 'value' => sprintf("🪙 %s", $quiz->amount * 2), 'inline' => 'true'])
            ->addField(['name' => 'Bilhete', 'value' => sprintf("(C$) %s", $quiz->amount), 'inline' => 'true']);

        $choices = json_decode($quiz->alternatives);
        $embed->addFieldValues(sprintf('A) %s', $choices[0]), '')
            ->addFieldValues(sprintf('B) %s', $choices[1]), '')
            ->addFieldValues(sprintf('C) %s', $choices[2]), '')
            ->addFieldValues(sprintf('D) %s', $choices[3]), '');

        return $embed;
    }

    private function betQuiz(
        $userDiscordId,
        $choice,
        $rouletteId,
        Interaction $interaction,
        $roulette,
        $amountBet,
        &$gameData,
        $userDiscord,
        Interaction $interactionUser
    )
    {

    }

    private function voiceDownload(string $url): string|null
    {
        $client = new HttpClient([
            'exceptions' => true,
        ]);

        try{
            $request = new Request('GET', $url);
            $response = $client->send($request);
            $data = $response->getBody()->getContents();
            $filename = sprintf("%s/temp_audio/%s.mp3", realpath(__DIR__ . '/../../../../'), date('d-m-Y_H-i-s-m-u'));
            $this->discord->getLogger()->info('Saving audio file: ' . $filename);
            file_put_contents($filename, $data);

            return $filename;
        }catch (\Exception $e){
            $this->discord->getLogger()->error($e);
            return null;
        }
    }
}