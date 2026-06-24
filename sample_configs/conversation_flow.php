<?php

declare(strict_types=1);

return [
    'id' => 'support_intake',
    'start' => 'choose_topic',
    'states' => [
        'choose_topic' => [
            'prompt' => 'Ask which support topic the user wants to continue.',
            'allowed_intents' => ['shipping.intent', 'account.intent'],
            'fallback_intent' => 'flow.recovery.intent',
            'fallback' => 'choose_topic',
            'transitions' => [
                'shipping.intent' => 'shipping_details',
                'account.intent' => 'account_details',
            ],
        ],
        'shipping_details' => [
            'prompt' => 'Collect shipping details and ask for confirmation.',
            'allowed_intents' => ['confirm.intent', 'cancel.intent'],
            'fallback_intent' => 'flow.recovery.intent',
            'fallback' => 'shipping_details',
            'transitions' => [
                'confirm.intent' => 'complete',
                'cancel.intent' => 'choose_topic',
            ],
        ],
        'account_details' => [
            'prompt' => 'Collect account details and ask for confirmation.',
            'allowed_intents' => ['confirm.intent', 'cancel.intent'],
            'fallback_intent' => 'flow.recovery.intent',
            'fallback' => 'account_details',
            'transitions' => [
                'confirm.intent' => 'complete',
                'cancel.intent' => 'choose_topic',
            ],
        ],
        'complete' => [
            'prompt' => 'Close the flow with a short confirmation.',
            'allowed_intents' => [],
            'transitions' => [],
            'complete' => true,
        ],
    ],
];
