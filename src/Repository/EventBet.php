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

    public function all() : array
    {
        return $this->db->query("SELECT * FROM events_bets");
    }

    public function create(int $discordId, int $eventId, string $choiceKey, float $amount) : bool
    {
        $user = $this->userRepository->getByDiscordId($discordId);
        $choiceId = $this->eventChoiceRepository->getByEventIdAndChoice($eventId, $choiceKey);
        $userId = $user[0]['id'];

        $this->db->beginTransaction();

        $createEvent = $this->db->query(
            'INSERT INTO events_bets (user_id, event_id, choice_id, amount) VALUES (:user_id, :event_id, :choice_id, :amount)',
            [
                'user_id' => $userId,
                'event_id' => $eventId,
                'choice_id' => $choiceId[0]['id'],
                'amount' => $amount,
            ]
        );

        $createUserBetHistory = $this->userCoinHistoryRepository->create($userId, -$amount, 'Bet', $eventId);

        $this->db->commit();

        return $createEvent && $createUserBetHistory;
    }

    public function getOpenBetsByDiscordIdAndEvent(int $discordId, int $eventId) : array
    {
        return $this->db->query(
            sprintf("SELECT
                    eb.*
                FROM events_bets eb
                JOIN events e ON e.id = eb.event_id
                WHERE
                    eb.user_id = :user_id
                    AND eb.event_id = :event_id
                    AND e.status = %s
            ", Event::OPEN),
            [
                'user_id' => $discordId,
                'event_id' => $eventId
            ]
        );
    }

    public function alreadyBetted(int $discordId, int $eventId) : int
    {
        $user = $this->userRepository->getByDiscordId($discordId);

        return count($this->getOpenBetsByDiscordIdAndEvent($user[0]['id'], $eventId)) > 0;
    }

    public function getBetsByEventId(int $eventId) : array
    {
        return $this->db->query(
            "SELECT
                    eb.user_id AS user_id,
                    eb.amount AS amount,
                    u.discord_user_id AS discord_user_id,
                    u.discord_username AS discord_username,
                    ec.choice_key
                FROM events_bets eb
                JOIN users u ON u.id = eb.user_id
                JOIN events_choices ec ON ec.id = eb.choice_id
                WHERE
                    eb.event_id = :event_id
            ",
            [
                'event_id' => $eventId
            ]
        );
    }
}
