<?php

use Discord\Parts\Interactions\Command\Option;

return [
    'name' => 'ensinar',
    'description' => 'Ensine algo ao nosso recruta',
    'options' => [
        [
            'type' => Option::STRING,
            'name' => 'conteúdo',
            'description' => 'Fonte de conhecimento',
            'required' => true,
        ],
    ]
];
