<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'quiz',
    'description' => 'Gerencia Quiz do recruta',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'criar',
            'description' => 'Criar um Quiz',
            'options' => [
                [
                    'type' => Option::STRING,
                    'name' => 'tema',
                    'description' => 'Tema/tópico do Quiz',
                    'required' => true,
                ],
                [
                    'type' => Option::NUMBER,
                    'name' => 'valor',
                    'description' => 'Quantidade de coins para participar do Quiz',
                    'required' => true,
                ],
            ]
        ],
    ]
];
