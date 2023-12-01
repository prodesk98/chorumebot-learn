<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'top',
    'description' => 'Lista de TOPs',
    'options' => [
        [
            'type' => Option::SUB_COMMAND,
            'name' => 'apostadores',
            'description' => 'Lista minhas apostas mais recentes',
        ],
    ]
];
