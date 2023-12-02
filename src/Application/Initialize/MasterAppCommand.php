<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'mestre',
    'description' => 'Pergunte ao mestre',
    'options' => [
        [
            'type' => Option::STRING,
            'name' => 'pergunta',
            'description' => 'Pergunta para o mestre',
            'required' => true,
        ],
    ]
];
