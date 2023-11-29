<?php

namespace Chorume\Repository;

use Chorume\Repository\EventBet;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

class Event extends Repository
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
        protected EventBet|Null $eventBetRepository = null,
        protected EventChoice|Null $eventChoiceRepository = null,
        protected UserCoinHistory|Null $userCoinHistoryRepository = null
    )
    {
        $this->eventBetRepository = $eventBetRepository ?? new EventBet($db);
        $this->eventChoiceRepository = $eventChoiceRepository ?? new EventChoice($db);
        $this->userCoinHistoryRepository = $userCoinHistoryRepository ?? new UserCoinHistory($db);

        parent::__construct($db);
    }

    public function all()
    {
        $result = $this->db->select("SELECT * FROM events");

        return $result;
    }

    public function getEventById(int $eventId)
    {
        $result = $this->db->select(
            "SELECT * FROM events WHERE id = ?",
            [
                [ 'type' => 'i', 'value' => $eventId ]
            ]
        );

        return $result;
    }

    public function create(string $eventName, string $optionA, string $optionB)
    {
        $createEvent = $this->db->query('INSERT INTO events (name, status) VALUES (?, ?)', [
            [ 'type' => 's', 'value' => $eventName ],
            [ 'type' => 's', 'value' => self::OPEN ],
        ]);

        $this->db->query('INSERT INTO events_choices (`event_id`, `choice_key`, `description`) VALUES (?, ?, ?)', [
            [ 'type' => 'i', 'value' => $createEvent->insert_id ],
            [ 'type' => 's', 'value' => 'A' ],
            [ 'type' => 's', 'value' => $optionA ],
        ]);

        $this->db->query('INSERT INTO events_choices (`event_id`, `choice_key`, `description`) VALUES (?, ?, ?)', [
            [ 'type' => 'i', 'value' => $createEvent->insert_id ],
            [ 'type' => 's', 'value' => 'B' ],
            [ 'type' => 's', 'value' => $optionB ],
        ]);

        return $createEvent;
    }

    public function closeEvent(int $eventId)
    {
        $data = [
            [ 'type' => 's', 'value' => self::CLOSED ],
            [ 'type' => 'i', 'value' => $eventId ],
        ];

        $createEvent = $this->db->query('UPDATE events SET status = ? WHERE id = ?', $data);

        return $createEvent;
    }

    public function finishEvent(int $eventId)
    {
        $data = [
            [ 'type' => 's', 'value' => self::PAID ],
            [ 'type' => 'i', 'value' => $eventId ],
        ];

        $createEvent = $this->db->query('UPDATE events SET status = ? WHERE id = ?', $data);

        return $createEvent;
    }

    public function canBet(int $eventId)
    {
        $result = $this->db->select(
            "SELECT * FROM events WHERE id = ? AND status NOT IN (?, ?)",
            [
                [ 'type' => 'i', 'value' => $eventId ],
                [ 'type' => 'i', 'value' => self::CLOSED ],
                [ 'type' => 'i', 'value' => self::PAID ],
            ]
        );

        return empty($result);
    }

    public function listEventsChoicesByStatus(int|array $status)
    {
        $status = is_array($status) ? implode(',', $status) : $status;

        $results = $this->db->select("
            SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.status AS event_status,
                ec.choice_key AS choice_option,
                ec.description AS choice_description
            FROM events_choices ec
            JOIN events e ON e.id = ec.event_id
            WHERE e.status IN (?)
        ", [
            [ 'type' => 's', 'value' => $status ],
        ]);

        return empty($results) ? [] : $results;
    }

    public function getEventDataById(int $eventId)
    {
        $results = $this->db->select("
            SELECT
                e.id AS event_id,
                e.name AS event_name,
                e.status AS event_status,
                ec.choice_key AS choice_option,
                ec.description AS choice_description
            FROM events_choices ec
            JOIN events e ON e.id = ec.event_id
            WHERE e.id = ?
        ", [
            [ 'type' => 'i', 'value' => $eventId ],
        ]);

        return empty($results) ? [] : $results;
    }

    public function listEventsOpen()
    {
        $results = $this->listEventsChoicesByStatus([self::OPEN]);

        if (empty($results)) {
            return [];
        }

        return $this->normalizeEventChoices($results);
    }

    public function listEventsClosed()
    {
        $results = $this->listEventsChoicesByStatus([self::CLOSED]);

        if (empty($results)) {
            return [];
        }

        return $this->normalizeEventChoices($results);
    }

    public function listEventById(int $eventId)
    {
        $results = $this->getEventDataById($eventId);

        if (empty($results)) {
            return [];
        }

        return $this->normalizeEventChoices($results);
    }

    public function normalizeEventChoices(array $eventChoices)
    {
        return array_reduce($eventChoices, function ($acc, $item) {
            if (($subItem = array_search($item['event_name'], array_column($acc, 'event_name'))) === false) {
                $acc[] = [
                    'event_id' => $item['event_id'],
                    'event_name' => $item['event_name'],
                    'event_status' => $item['event_status'],
                    'choices' => [
                        [
                            'choice_option' => $item['choice_option'],
                            'choice_description' => $item['choice_description'],
                        ]
                    ]
                ];

                return $acc;
            }

            $acc[$subItem]['choices'][] = [
                'choice_option' => $item['choice_option'],
                'choice_description' => $item['choice_description'],
            ];

            return $acc;
        }, []);
    }

    public function updateEventWithWinner(int $choiceId, int $eventId)
    {
        $createEvent = $this->db->query('UPDATE events SET status = ?, winner_choice_id = ? WHERE id = ?', [
            [ 'type' => 's', 'value' => self::PAID ],
            [ 'type' => 'i', 'value' => $choiceId, ],
            [ 'type' => 'i', 'value' => $eventId ],
        ]);

        return $createEvent;
    }

    public function calculateOdds(int $eventId)
    {
        $bets = $this->eventBetRepository->getBetsByEventId($eventId);
        $totalBetsA = array_reduce($bets, fn ($acc, $item) => $item['choice_key'] === 'A' ? $acc += $item['amount'] : $acc, 0);
        $totalBetsB = array_reduce($bets, fn ($acc, $item) => $item['choice_key'] === 'B' ? $acc += $item['amount'] : $acc, 0);

        $oddsA = $totalBetsA !== 0 ? ($totalBetsB / $totalBetsA) + 1: 1;
        $oddsB = $totalBetsB !== 0 ? ($totalBetsA / $totalBetsB) + 1: 1;

        return [
            'oddsA' => $oddsA,
            'oddsB' => $oddsB,
        ];
    }

    public function payoutEvent(int $eventId, string $winnerChoiceKey)
    {
        $winners = [];
        $bets = $this->eventBetRepository->getBetsByEventId($eventId);
        $choiceId = $this->eventChoiceRepository->getChoiceByEventIdAndKey($eventId, $winnerChoiceKey);
        $totalBetsA = array_reduce($bets, fn ($acc, $item) => $item['choice_key'] === 'A' ? $acc += $item['amount'] : $acc, 0);
        $totalBetsB = array_reduce($bets, fn ($acc, $item) => $item['choice_key'] === 'B' ? $acc += $item['amount'] : $acc, 0);

        if ($totalBetsA === 0 && $totalBetsB === 0) {
            return [];
        }

        $this->updateEventWithWinner($choiceId[0]['id'], $eventId);

        $oddsA = $totalBetsA !== 0 ? ($totalBetsB / $totalBetsA) + 1: 1;
        $oddsB = $totalBetsB !== 0 ? ($totalBetsA / $totalBetsB) + 1: 1;

        foreach ($bets as $bet) {
            if ($bet['choice_key'] !== $winnerChoiceKey) continue;

            $betPayout = $winnerChoiceKey === 'A' ? round(($bet['amount'] * $oddsA), 2) : round($bet['amount'] * $oddsB, 2);
            $this->userCoinHistoryRepository->create($bet['user_id'], $betPayout, 'Event', $eventId);

            $winners[] = [
                'discord_user_id' => $bet['discord_user_id'],
                'discord_username' => $bet['discord_username'],
                'choice_key' => $bet['choice_key'],
                'earnings' => $betPayout,
            ];
        }

        return $winners;
    }
}
