<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'perguntar',
    'description' => 'Pergunte ao nosso recruta',
    'options' => [
        [
            'type' => Option::STRING,
            'name' => 'pergunta',
            'description' => 'Faça uma pergunta ao recruta',
            'required' => true,
        ],
    ]
];
