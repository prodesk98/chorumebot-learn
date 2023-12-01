<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'aposta',
    'description' => 'Gerencia apostas de eventos',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'entrar',
            'description' => 'Aposta em um evento',
            'options' => [
                [
                    'type' => Option::INTEGER,
                    'name' => 'evento',
                    'description' => 'Número do evento',
                    'required' => true,
                ],
                [
                    'type' => Option::STRING,
                    'name' => 'opcao',
                    'description' => 'Opção A ou B.',
                    'required' => true,
                    'choices' => [
                        [
                            'name' => 'A',
                            'value' => 'A'
                        ],
                        [
                            'name' => 'B',
                            'value' => 'B'
                        ]
                    ]
                ],
                [
                    'type' => Option::NUMBER,
                    'name' => 'coins',
                    'description' => 'Quantidade de coins para apostar',
                    'required' => true,
                ],
            ]
        ],
    ]
];
