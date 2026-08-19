<?php

declare(strict_types=1);

return [
    'profiles' => [
        'support-guide' => [
            'id' => 'support-guide',
            'identity' => [
                'name' => 'Support Guide',
                'description' => 'Provides bounded support responses.',
            ],
            'role' => 'assistant',
            'domain_knowledge_refs' => ['knowledge:support'],
            'conversational_policies' => [
                'tone' => 'calm',
                'no_secrets' => true,
            ],
            'supported_intents' => ['support.status'],
            'declared_capabilities' => ['conversation.classify'],
            'context_permissions' => ['conversation_history', 'memory'],
        ],
    ],
    'handoff' => [
        'profile' => 'support-guide',
        'required_capabilities' => ['conversation.classify'],
        'context' => [
            'context_refs' => [
                'conversation_history' => 'history:turn-4',
                'memory' => 'memory:episode-2',
                'private_notes' => 'private:operator-only',
            ],
            'current_intent' => 'support.status',
            'unresolved_questions' => ['confirm delivery window'],
            'confidence' => 0.84,
            'provenance' => ['source' => 'deterministic-fixture'],
        ],
    ],
    'outcome_fixtures' => [
        'accepted' => [
            'profile' => 'support-guide',
            'required_capabilities' => ['conversation.classify'],
            'context' => [
                'context_refs' => [
                    'conversation_history' => 'history:turn-4',
                    'memory' => 'memory:episode-2',
                    'private_notes' => 'private:operator-only',
                ],
                'current_intent' => 'support.status',
                'unresolved_questions' => ['confirm delivery window'],
                'confidence' => 0.84,
                'provenance' => ['source' => 'deterministic-fixture'],
            ],
            'expected_status' => 'accepted',
        ],
        'clarification' => [
            'profile' => 'support-guide',
            'required_capabilities' => [],
            'context' => [
                'context_refs' => ['memory' => 'memory:episode-2'],
                'current_intent' => '',
                'unresolved_questions' => ['which status should be checked?'],
                'confidence' => 0.0,
                'provenance' => ['source' => 'deterministic-fixture'],
            ],
            'expected_status' => 'clarification',
        ],
        'rejected' => [
            'profile' => 'support-guide',
            'required_capabilities' => ['filesystem.write'],
            'context' => [
                'context_refs' => ['private_notes' => 'private:operator-only'],
                'current_intent' => 'support.status',
                'unresolved_questions' => [],
                'confidence' => 0.84,
                'provenance' => ['source' => 'deterministic-fixture'],
            ],
            'expected_status' => 'rejected',
        ],
        'failure' => [
            'profile_data' => ['id' => 'incomplete'],
            'required_capabilities' => [],
            'context' => [
                'context_refs' => [],
                'current_intent' => 'support.status',
                'unresolved_questions' => [],
                'confidence' => 0.84,
                'provenance' => ['source' => 'deterministic-fixture'],
            ],
            'expected_status' => 'failure',
        ],
    ],
];
