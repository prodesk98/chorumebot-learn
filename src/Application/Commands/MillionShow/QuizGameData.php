<?php

namespace Chorume\Application\Commands\MillionShow;

class QuizGameData
{
    public array $players;
    public int $amountA = 0;
    public int $amountB = 0;
    public int $amountC = 0;
    public int $amountD = 0;
    public int $amountTotal = 0;

    public function __construct(
        public int $quizId = 0
    )
    {
        $this->players = [];
        $this->amountA = 0;
        $this->amountB = 0;
        $this->amountC = 0;
        $this->amountD = 0;
        $this->amountTotal = 0;
    }

}