<?php

namespace Chorume\Repository;

use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

class RouletteBet extends Repository
{
    public const OPEN = 1;
    public const CLOSED = 2;
    public const CANCELED = 3;
    public const PAID = 4;

    public const LABEL = [
        self::OPEN => 'Aberto',
        self::CLOSED => 'Fechado',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Pago',
    ];

    public const LABEL_LONG = [
        self::OPEN => 'Aberto para apostas',
        self::CLOSED => 'Fechado para apostas',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Apostas pagas',
    ];
    public function __construct(
        $db,
    )
    {
        $this->userRepository = $userRepository ?? new User($db);
        $this->eventChoiceRepository = $eventChoiceRepository ?? new EventChoice($db);
        $this->userCoinHistoryRepository = $userCoinHistoryRepository ?? new UserCoinHistory($db);
        parent::__construct($db);
    }
    public function createRouletteBetEvent(int $userId, int $rouletteId, int $betAmount, int $choice)
    {
        $createBetEvent = $this->db->query('INSERT INTO roulette_bet (user_id, roulette_id, bet_amount, choice, created_at) VALUES (?, ?, ?, ?, ?)', [
            [ 'type' => 'i', 'value' => $userId ],
            [ 'type' => 'i', 'value' => $rouletteId ],
            [ 'type' => 'i', 'value' => $betAmount ],
            [ 'type' => 'i', 'value' => $choice ],
            [ 'type' => 's', 'value' => date('Y-m-d H:i:s') ], 
        ]);
    }
    
  
 
}