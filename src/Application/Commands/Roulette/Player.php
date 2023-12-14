<?php

namespace Chorume\Application\Commands\Roulette;

class Player
{
    public function __construct(
        public string $user,
        public int $bet,
        public string $choice,
        public string $userName
    )
    {
    }
}