<?php

namespace Chorume\Repository;

use Chorume\Repository\EventBet;
use Chorume\Repository\EventChoice;
use Chorume\Repository\UserCoinHistory;

/**
 * table: talks
 *
 * id (int)
 * triggertext (varchar)
 * type (varchar)
 * answer (json)
 * status (tinyint)
 *
 */

class Talk extends Repository
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    public function create(string $triggertext, string $type, string $answer)
    {
        $result = $this->db->query('INSERT INTO talks (triggertext, type, answer, status) VALUES (?, ?, ?, ?)', [
            [ 'type' => 's', 'value' => $triggertext ],
            [ 'type' => 's', 'value' => $type ],
            [ 'type' => 's', 'value' => $answer ],
            [ 'type' => 'i', 'value' => self::STATUS_ACTIVE ]
        ]);

        return $result;
    }

    public function findById(int $id)
    {
        $result = $this->db->select(
            "SELECT * FROM talks WHERE id = ?",
            [
                [ 'type' => 'i', 'value' => $id ]
            ]
        );

        return $result;
    }

    public function findTrigger(string $triggertext)
    {
        $result = $this->db->select(
            "SELECT answer FROM talks WHERE triggertext = ? WHERE status = ?",
            [
                [ 'type' => 's', 'value' => $triggertext ],
                [ 'type' => 'i', 'value' => self::STATUS_ACTIVE ]
            ]
        );

        return $result;
    }

    public function listAllTriggers()
    {
        $result = $this->db->select(
            "SELECT id, triggertext FROM talks WHERE status = ?",
            [
                [ 'type' => 'i', 'value' => self::STATUS_ACTIVE ]
            ]
        );

        return $result;
    }
}
