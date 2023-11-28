<?php

namespace Chorume\Repository;

class UserCoinHistory extends Repository
{
    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function all()
    {
        $result = $this->db->select("SELECT * FROM users_coins_history");

        return $result;
    }

    public function create(int $userId, float $amount, string $type)
    {
        $result = $this->db->query(
            "INSERT INTO users_coins_history (user_id, amount, type) VALUES (?, ?, ?)",
            [
                [ 'type' => 'i', 'value' => $userId ],
                [ 'type' => 'd', 'value' => $amount ],
                [ 'type' => 's', 'value' => $type ]
            ]
        );

        return $result;
    }

    public function listTop10()
    {
        $result = $this->db->select("
            SELECT
                SUM(uch.amount) AS total_coins,
                u.discord_user_id
            FROM users_coins_history uch
            JOIN users u ON uch.user_id = u.id
            GROUP BY uch.user_id
            ORDER BY total_coins DESC
            LIMIT 10
        ");

        return $result;
    }

    /**
     * do not performs any validation here, so be careful as this method can be used to "steal" coins
     */
    public function transfer(int $fromId, float $amount, int $toId)
    {
        $type = 'Transfer';
        $result = $this->db->query(
            "INSERT INTO users_coins_history (user_id, amount, type) VALUES (?, ?, ?), (?, ?, ?)",
            [
                [ 'type' => 'i', 'value' => $fromId ],
                [ 'type' => 'd', 'value' => -$amount ],
                [ 'type' => 's', 'value' => $type ],
                [ 'type' => 'i', 'value' => $toId ],
                [ 'type' => 'd', 'value' => $amount ],
                [ 'type' => 's', 'value' => $type ]
            ]
        );

        return $result;
    }
}