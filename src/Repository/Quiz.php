<?php

namespace Chorume\Repository;

use Chorume\Repository\QuizChoice;
use Chorume\Repository\UserCoinHistory;

class Quiz extends Repository
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
        self::OPEN => 'Aberto para escolha',
        self::CLOSED => 'Fechado para escolha',
        self::CANCELED => 'Cancelado',
        self::PAID => 'Escolhas pagas',
    ];

    public const A = 1;
    public const B = 2;
    public const C = 3;
    public const D = 4;

    private QuizChoice $quizChoiceRepository;
    private UserCoinHistory $userCoinHistoryRepository;

    public function __construct($db)
    {
        $this->quizChoiceRepository = new QuizChoice($db);
        $this->userCoinHistoryRepository = new UserCoinHistory($db);

        parent::__construct($db);
    }

    public function all() : array
    {
        return $this->db->query("SELECT * FROM quiz");
    }

    public function getQuizById(int $quizId): array
    {
        return $this->db->query(
            "SELECT * FROM quiz WHERE id = :quiz_id",
            [
                'quiz_id' => $quizId
            ]
        );
    }

    public function createEvent(string $theme, float $value, int $truth, string $question, array $alternatives, string|null $voice_url) : int|bool
    {
        $createEvent = $this->db->query(
            'INSERT INTO quiz (
                  status, theme, amount, 
                  truth,  question, alternatives,
                  voice_url) VALUES (:status, :theme, :amount, :truth, :question, :alternatives, :voice_url)',
            [
                "status" => self::OPEN,
                "theme" => $theme,
                "amount" => $value,
                "truth" => $truth,
                "question" => $question,
                "alternatives" => json_encode($alternatives),
                "voice_url" => $voice_url
            ]
        );

        return $createEvent ? $this->db->getLastInsertId() : false;
    }
}