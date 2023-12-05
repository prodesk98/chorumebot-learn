<?php

namespace Chorume\Repository;

class User extends Repository
{
    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function all()
    {
        $result = $this->db->select("SELECT * FROM users");

        return $result;
    }

    public function getByDiscordId(int $discordId)
    {
        $result = $this->db->select(
            "SELECT * FROM users WHERE discord_user_id = ?",
            [
                [ 'type' => 'i', 'value' => $discordId ]
            ]
        );

        return $result;
    }

    public function getUsersByDiscordIds(array $ids)
    {
        $idsPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $ids = [];

        foreach ($ids as $id) {
            $ids[] = [ 'type' => 'i', 'value' => $id ];
        }

        $result = $this->db->select(
            "
                SELECT
                    *
                FROM users uch
                WHERE
                    uch.id IN ($idsPlaceholders)
            ",
            $ids
        );

        return empty($result);
    }

    public function giveInitialCoins(int $discordId, $discordUsername)
    {
        $createUser = $this->db->query('INSERT INTO users (discord_user_id, discord_username, received_initial_coins) VALUES (?, ?, ?)', [
            [ 'type' => 'i', 'value' => $discordId ],
            [ 'type' => 's', 'value' => $discordUsername ],
            [ 'type' => 'i', 'value' => 1 ]
        ]);

        $giveCoins = $this->db->query('INSERT INTO users_coins_history (user_id, amount, type) VALUES (?, ?, ?)', [
            [ 'type' => 'i', 'value' => $createUser->insert_id ],
            [ 'type' => 'i', 'value' => 100 ],
            [ 'type' => 's', 'value' => 'Initial' ]
        ]);

        return $giveCoins;
    }

    public function giveDailyCoins(int $discordId, float $amount)
    {
        $user = $this->getByDiscordId($discordId);

        $giveCoins = $this->db->query('INSERT INTO users_coins_history (user_id, amount, type) VALUES (?, ?, ?)', [
            [ 'type' => 'i', 'value' => $user[0]['id'] ],
            [ 'type' => 'i', 'value' => $amount ],
            [ 'type' => 's', 'value' => 'Daily' ]
        ]);

        return $giveCoins;
    }

    public function canReceivedDailyCoins(int $discordId)
    {
        $result = $this->db->select(
            "
                SELECT
                    *
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE
                    u.discord_user_id = ?
                    AND uch.type = 'Daily'
                    AND DATE(uch.created_at) = CURDATE();
            ",
            [
                [ 'type' => 'i', 'value' => $discordId ]
            ]
        );

        return empty($result);
    }

    public function getCurrentCoins(int $discordId)
    {
        $result = $this->db->select(
            "
                SELECT
                    SUM(amount) AS total
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE u.discord_user_id = ?
            ",
            [
                [ 'type' => 'i', 'value' => $discordId ]
            ]
        );

        return $result;
    }

    public function hasAvailableCoins(int $discordId, int $amount)
    {
        $result = $this->db->select(
            "
                SELECT
                    SUM(amount) AS total
                FROM users_coins_history uch
                JOIN users u ON u.id = uch.user_id
                WHERE
                    u.discord_user_id = ?
            ",
            [
                [ 'type' => 'i', 'value' => $discordId ]
            ]
        );

        return $result[0]['total'] >= $amount;
    }

    public function userExistByDiscordId(int $discordId)
    {
        $result = $this->db->select(
            "SELECT * FROM users WHERE discord_user_id = ?",
            [
                [ 'type' => 'i', 'value' => $discordId ]
            ]
        );

        return !empty($result);
    }
}
