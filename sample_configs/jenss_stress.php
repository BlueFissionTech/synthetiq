<?php

declare(strict_types=1);

return [
    'purpose' => 'JenSS/Jenerator stress fixtures for SynthetIQ route catalogs, confidence gates, and reusable feedback loops.',
    'runner' => 'BlueFission\\Jenerator\\Parsing\\JenssParser with BlueFission\\Jenerator\\Runtime\\Interpreter',
    'working_directory' => 'project_root',
    'scripts' => [
        [
            'path' => 'examples/jenss/router-catalog-stress.jss',
            'features' => [
                'develation.data JSON fixture loading',
                'world/domain sections',
                'Naive Bayes intent training',
                'Markov template continuity',
                'statement confidence',
                'feedback policy review gates',
            ],
            'expected_messages' => [
                'Scenario: SynthetIQ JenSS router stress',
                'Goal: Keep dialogue routing bounded and explainable',
                'Probe: weather status tomorrow',
                'Predicted intent: weather.intent',
                'Model confidence: 0.22222222222222',
                'Next template token: requests',
                'Review: required',
                'Reason: Fallback sensitivity requires human review.',
            ],
        ],
        [
            'path' => 'examples/jenss/evaluation-feedback-stress.jss',
            'features' => [
                'evaluation gate summaries',
                'accepted correction feedback',
                'Naive Bayes probe prediction',
                'low-confidence review policy',
            ],
            'expected_messages' => [
                'Goal: Convert accepted dialogue corrections into reusable routing data',
                'Probe: goodbye for now',
                'Predicted intent: goodbye.intent',
                'Prediction confidence: 0.25',
                'Goodbye feedback: 1',
                'Unknown feedback: -1',
                'Feedback: intent:greeting.intent=1.00 | intent:status.intent=1.00 | intent:unknown.intent=-1.00 | intent:goodbye.intent=1.00',
                'Evaluation: matched=yes score=0.72 strategy=intent_regression_gate',
                'Review: required',
                'Reason: Signal confidence is below policy threshold.',
            ],
        ],
    ],
];
