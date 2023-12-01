<?php

namespace Chorume\Repository;

use Chorume\Repository\User;
use Chorume\Repository\Event;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

class EventBet extends Repository
{
    public function __construct(
        $db,
        protected User|null $userRepository = null,
        protected EventChoice|null $eventChoiceRepository = null,
        protected UserCoinHistory|null $userCoinHistoryRepository = null
    ) {
        $this->userRepository = $userRepository ?? new User($db);
        $this->eventChoiceRepository = $eventChoiceRepository ?? new EventChoice($db);
        $this->userCoinHistoryRepository = $userCoinHistoryRepository ?? new UserCoinHistory($db);

        parent::__construct($db);
    }

    public function all()
    {
        $result = $this->db->select("SELECT * FROM events_bets");

        return $result;
    }

    public function create(int $discordId, int $eventId, string $choiceKey, float $amount)
    {
        $user = $this->userRepository->getByDiscordId($discordId);
        $choiceId = $this->eventChoiceRepository->getByEventIdAndChoice($eventId, $choiceKey);
        $userId = $user[0]['id'];

        $createEvent = $this->db->query('INSERT INTO events_bets (user_id, event_id, choice_id, amount) VALUES (?, ?, ?, ?)', [
            [ 'type' => 'i', 'value' => $userId ],
            [ 'type' => 'i', 'value' => $eventId ],
            [ 'type' => 'i', 'value' => $choiceId[0]['id'] ],
            [ 'type' => 'd', 'value' => $amount ]
        ]);

        $createUserBetHistory = $this->userCoinHistoryRepository->create($userId, -$amount, 'Bet');

        return $createEvent && $createUserBetHistory;
    }

    public function getOpenBetsByDiscordIdAndEvent(int $discordId, int $eventId)
    {
        $results = $this->db->select(
            sprintf("
                SELECT
                    eb.*
                FROM events_bets eb
                JOIN events e ON e.id = eb.event_id
                WHERE
                    eb.user_id = ?
                    AND eb.event_id = ?
                    AND e.status = %s
            ", Event::OPEN),
            [
                [ 'type' => 'i', 'value' => $discordId ],
                [ 'type' => 'i', 'value' => $eventId ]
            ]
        );

        return $results;
    }

    public function alreadyBetted(int $discordId, int $eventId)
    {
        $user = $this->userRepository->getByDiscordId($discordId);

        return count($this->getOpenBetsByDiscordIdAndEvent($user[0]['id'], $eventId)) > 0;
    }

    public function getBetsByEventId(int $eventId)
    {
        $results = $this->db->select(
            "
                SELECT
                    eb.user_id AS user_id,
                    eb.amount AS amount,
                    u.discord_user_id AS discord_user_id,
                    u.discord_username AS discord_username,
                    ec.choice_key
                FROM events_bets eb
                JOIN users u ON u.id = eb.user_id
                JOIN events_choices ec ON ec.id = eb.choice_id
                WHERE
                    eb.event_id = ?
            ",
            [
                [ 'type' => 'i', 'value' => $eventId ]
            ]
        );

        return $results;
    }
}
