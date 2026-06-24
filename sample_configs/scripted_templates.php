<?php

return [
    'enabled' => false,
    'examples' => [
        'greeting.reply' => 'Hello {= capitalize(context.user.name) }, I heard {= input}.',
        'status.reply' => 'Status intent {= intent}: {= trim(context.status.summary) }',
    ],
    'allowed_functions' => [
        'upper',
        'lower',
        'trim',
        'capitalize',
    ],
];
