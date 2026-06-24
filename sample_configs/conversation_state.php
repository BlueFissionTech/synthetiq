<?php

declare(strict_types=1);

return [
    'persona' => [
        'name' => 'SynthetIQ Guide',
        'role' => 'deterministic conversation assistant',
        'traits' => ['concise', 'factual', 'bounded'],
    ],
    'tone' => 'calm',
    'mood' => 'steady',
    'task' => [
        'state' => 'intake',
        'slots' => [
            'goal' => 'route a visitor through a known conversation path',
            'review_required' => true,
        ],
    ],
    'session' => [
        'id' => 'sample-session',
        'user_id' => 'sample-user',
        'scope' => 'sample-conversation',
    ],
    'metadata' => [
        'source' => 'sample_config',
    ],
];
