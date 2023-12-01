<?php

namespace Chorume\Repository;

use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

class Roulette extends Repository
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
    ) {
        parent::__construct($db);
    }

    public function createEvent(string $eventName)
    {

        $createEvent = $this->db->query('INSERT INTO roulette (status, description) VALUES (?, ?)', [
            ['type' => 'i', 'value' => self::OPEN],
            ['type' => 's', 'value' => $eventName],
        ]);
        return $createEvent;
    }

    public function close(int $eventId)
    {
        $data = [
            ['type' => 's', 'value' => self::CLOSED],
            ['type' => 'i', 'value' => $eventId],
        ];

        $createEvent = $this->db->query('UPDATE roulette SET status = ? WHERE id = ?', $data);

        return $createEvent;
    }
    public function finish(int $eventId)
    {
        $data = [
            ['type' => 's', 'value' => self::PAID],
            ['type' => 'i', 'value' => $eventId],
        ];

        $createEvent = $this->db->query('UPDATE roulette SET status = ? WHERE id = ?', $data);

        return $createEvent;
    }
}
