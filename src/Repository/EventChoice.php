<?php

namespace Chorume\Repository;

class EventChoice extends Repository
{
    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function all()
    {
        $result = $this->db->select("SELECT * FROM events_choices");

        return $result;
    }

    public function getByEventIdAndChoice(int $eventId, string $choiceKey)
    {
        $result = $this->db->select(
            "
                SELECT
                    *
                FROM events_choices
                WHERE
                    event_id = ?
                    AND choice_key = ?
            ",
            [
                [ 'type' => 'i', 'value' => $eventId ],
                [ 'type' => 's', 'value' => $choiceKey ]
            ]
        );

        return $result;
    }

    public function getChoiceByEventIdAndKey(int $eventId, string $choiceKey)
    {
        $result = $this->db->select(
            "
                SELECT
                    *
                FROM events_choices
                WHERE
                    event_id = ?
                    AND choice_key = ?
            ",
            [
                [ 'type' => 'i', 'value' => $eventId ],
                [ 'type' => 's', 'value' => $choiceKey ]
            ]
        );

        return $result;
    }
}