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
}