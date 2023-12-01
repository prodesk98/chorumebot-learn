<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'roleta',
    'description' => 'Gerencia Roletas para apostas',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'criar',
            'description' => 'Cria Roletas',
            'options' => [
                [
                    'type' => Option::STRING,
                    'name' => 'nome',
                    'description' => 'Nome da Roleta',
                    'required' => true,
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'iniciar',
            'description' => 'Inicia evento',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID do evento',
                    'required' => true,
                ],
            ]
        ],
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'fechar',
            'description' => 'Fecha Roleta e não recebe mais apostas',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'id',
                    'description' => 'ID da Roleta',
                    'required' => true,
                ],
            ]
        ],

        [
            'type' => Option::SUB_COMMAND,
            'name' => 'listar',
            'description' => 'Lista Roletas criados e pendentes para iniciar',
        ]
    ]
];
