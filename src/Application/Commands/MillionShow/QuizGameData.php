<?php

namespace Chorume\Application\Commands\MillionShow;

class QuizGameData
{

    public int $AmountA = 0;
    public int $AmountB = 0;
    public int $AmountC = 0;

    public int $AmountD = 0;

    public int $AmountTotal = 0;

    public array $players;

    public function __construct(
        public int $quizId = 0
    )
    {
        $this->AmountA = 0;
        $this->AmountB = 0;
        $this->AmountC = 0;
        $this->AmountD = 0;
        $this->AmountTotal = 0;
        $this->players = [];
    }

}