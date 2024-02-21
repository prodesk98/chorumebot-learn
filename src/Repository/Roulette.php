<?php

namespace Chorume\Repository;

use Chorume\Repository\RouletteBet;
use Chorume\Repository\User;
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

    public const GREEN = 1;
    public const BLACK = 2;
    public const RED = 3;


    public const LABEL_CHOICE = [
        self::GREEN => 'Escolha Verde!',
        self::BLACK => 'Escolha Preto!',
        self::RED => 'Escolha Vermelho!',
    ];

    private RouletteBet $rouletteBetRepository;
    private User $userRepository;
    private UserCoinHistory $userCoinHistoryRepository;

    public function __construct($db)
    {
        $this->rouletteBetRepository = new RouletteBet($db);
        $this->userRepository = new User($db);
        $this->userCoinHistoryRepository = new UserCoinHistory($db);

        parent::__construct($db);
    }

    public function createEvent(string $eventName, int $value, int $discordId) : int|bool
    {
        $user = $this->userRepository->getByDiscordId($discordId);
        $userId = $user[0]['id'];

        $createEvent = $this->db->query(
            'INSERT INTO roulette (created_by, status, description, amount) VALUES (:created_by, :status, :description, :amount)',
            [
                'created_by' => $userId,
                'status' => self::OPEN,
                'description' => $eventName,
                'amount' => $value,
            ]
        );

        return $createEvent ? $this->db->getLastInsertId() : false;
    }

    public function close(int $eventId) : bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::CLOSED,
                'event_id' => $eventId,
            ]
        );
    }

    public function finish(int $eventId) : bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::PAID,
                'event_id' => $eventId,
            ]
        );
    }

    public function listEventsOpen(int $limit = null) : array
    {
        return $this->normalizeRoulette($this->listEventsByStatus(['status_open' => self::OPEN], $limit));
    }

    public function listEventsClosed(int $limit = null) : array
    {

        return $this->normalizeRoulette($this->listEventsByStatus(['status_closed' => self::CLOSED], $limit));
    }

    public function listEventsPaid(int $limit = null) : array
    {
        return $this->normalizeRoulette($this->listEventsByStatus(['status_paid' => self::PAID], $limit));
    }

    public function listEventsByStatus(array $status, int $limit = null) : array
    {
        $statusKeys = implode(',', array_map(fn ($item) => ":{$item}", array_keys($status)));

        $params = [];
        $limitSQL = '';

        if ($limit) {
            $params['limit'] = (int) $limit;
            $limitSQL = "LIMIT 0, :limit";
        }

        return $this->db->query(
            "SELECT
                id AS roulette_id,
                description AS description,
                status AS status,
                choice AS choice,
                amount AS amount
            FROM roulette
            WHERE status IN ({$statusKeys}) ORDER BY id DESC $limitSQL
            ",
            [...$status, ...$params]
        );
    }

    public function normalizeRoulette(array $roulette) : array
    {
        return array_map(function ($item) {
            return [
                'roulette_id' => $item['roulette_id'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'choice' => $item['choice'],
                'status' => $item['status'],
            ];
        }, $roulette);
    }

    public function getRouletteById(int $rouletteId) : array
    {
        return $this->db->query(
            "SELECT * FROM roulette WHERE id = :event_id",
            [
                'event_id' => $rouletteId
            ]
        );
    }

    public function closeEvent(int $rouletteId) : bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status WHERE id = :event_id',
            [
                'status' => self::CLOSED,
                'event_id' => $rouletteId,
            ]
        );
    }

    public function payoutRoulette(int $rouletteId, $winnerChoiceKey) : array
    {
        $winners = [];
        $bets = $this->rouletteBetRepository->getBetsByEventId($rouletteId);
        $odd = 2;

        if ($winnerChoiceKey == Roulette::GREEN) {
            $odd = 14;
        }

        $this->updateRouletteWithWinner($winnerChoiceKey, $rouletteId);

        foreach ($bets as $bet) {
            $choiceKey =  $bet['choice_key'];

            if ($bet['choice_key'] !== $winnerChoiceKey) {
                continue;
            }

            $betPayout = $bet['amount'] * $odd;
            $this->userCoinHistoryRepository->create($bet['user_id'], $betPayout, 'RouletteBet', $rouletteId);

            $winners[] = [
                'discord_user_id' => $bet['discord_user_id'],
                'discord_username' => $bet['discord_username'],
                'choice_key' => $choiceKey,
                'earnings' => $betPayout,
            ];
        }

        return $winners;
    }

    public function updateRouletteWithWinner(int $choiceId, int $eventId) : bool
    {
        return $this->db->query(
            'UPDATE roulette SET status = :status, choice = :choice WHERE id = :event_id',
            [
                'status' => self::PAID,
                'choice' => $choiceId,
                'event_id' => $eventId,
            ]
        );
    }
}
