<?php

declare(strict_types=1);

use BlueFission\SynthetIQ\Scenes\SceneContract;

return [
    'proof_walkthrough' => [
        'version' => SceneContract::VERSION,
        'id' => 'proof_walkthrough',
        'title' => 'Proof Walkthrough',
        'summary' => 'Guides a visitor through a deterministic proof path with explicit fallback and handoff rules.',
        'voice_policy' => [
            'tone' => 'calm, concise, and factual',
            'allowed' => [
                'summarize known proof steps',
                'ask for one decision at a time',
                'state uncertainty plainly',
            ],
            'avoid' => [
                'inventing unavailable proof evidence',
                'claiming external review or approval',
            ],
        ],
        'public_safety' => [
            'constraints' => [
                'do not collect secrets or payment data',
                'do not promise completion outside configured handoff paths',
                'do not expose internal diagnostics unless explicitly mapped to public text',
            ],
            'escalation' => [
                'trigger' => 'visitor requests custom review, private data handling, or unsupported commitments',
                'handoff' => 'consultation_request',
            ],
        ],
        'handoff' => [
            'consultation_request' => [
                'label' => 'Consultation request',
                'required_fields' => ['name', 'organization', 'contact', 'goal'],
                'summary_template' => 'Visitor requested a guided follow-up for {{goal}}.',
            ],
        ],
        'states' => [
            [
                'id' => 'intro',
                'type' => SceneContract::TYPE_DIALOGUE,
                'prompt' => 'Introduce the proof path and ask which branch the visitor wants to inspect.',
                'choices' => [
                    ['id' => 'architecture', 'label' => 'Architecture', 'next' => 'architecture'],
                    ['id' => 'evidence', 'label' => 'Evidence', 'next' => 'evidence'],
                    ['id' => 'handoff', 'label' => 'Request follow-up', 'next' => 'consult'],
                ],
                'fallback' => [
                    'prompt' => 'Offer the available proof branches again.',
                    'next' => 'intro',
                ],
            ],
            [
                'id' => 'architecture',
                'type' => SceneContract::TYPE_DECISION,
                'prompt' => 'Summarize the architecture branch using only configured proof notes.',
                'choices' => [
                    ['id' => 'evidence', 'label' => 'Review evidence', 'next' => 'evidence'],
                    ['id' => 'restart', 'label' => 'Choose another branch', 'next' => 'intro'],
                ],
                'fallback' => [
                    'prompt' => 'Return to branch selection.',
                    'next' => 'intro',
                ],
            ],
            [
                'id' => 'evidence',
                'type' => SceneContract::TYPE_DECISION,
                'prompt' => 'List configured evidence markers and ask whether the visitor wants a follow-up.',
                'choices' => [
                    ['id' => 'consult', 'label' => 'Request follow-up', 'next' => 'consult'],
                    ['id' => 'restart', 'label' => 'Choose another branch', 'next' => 'intro'],
                ],
                'fallback' => [
                    'prompt' => 'Restate the available evidence actions.',
                    'next' => 'evidence',
                ],
            ],
            [
                'id' => 'consult',
                'type' => SceneContract::TYPE_HANDOFF,
                'prompt' => '',
                'fallback' => [
                    'prompt' => 'Collect only the configured handoff fields.',
                    'next' => 'consult',
                ],
                'handoff' => [
                    'target' => 'consultation_request',
                    'reason' => 'Visitor requested a guided follow-up.',
                ],
            ],
        ],
    ],
    'operator_assistant' => [
        'version' => SceneContract::VERSION,
        'id' => 'operator_assistant',
        'title' => 'Operator Assistant',
        'summary' => 'Supports a deterministic operational assistant flow with explicit review and handoff boundaries.',
        'voice_policy' => [
            'tone' => 'direct, neutral, and action-oriented',
            'allowed' => [
                'summarize configured status facts',
                'ask for missing task scope',
                'offer review-safe next actions',
            ],
            'avoid' => [
                'executing work without approval',
                'revealing private source details',
                'presenting guesses as verified facts',
            ],
        ],
        'public_safety' => [
            'constraints' => [
                'separate observation from action',
                'require approval for irreversible work',
                'redact private identifiers before public summaries',
            ],
            'escalation' => [
                'trigger' => 'operator requests a privileged, unsafe, or unsupported action',
                'handoff' => 'operator_review',
            ],
        ],
        'handoff' => [
            'operator_review' => [
                'label' => 'Operator review',
                'required_fields' => ['task', 'reason', 'risk', 'requested_action'],
                'summary_template' => 'Operator review required for {{requested_action}} because {{reason}}.',
            ],
        ],
        'states' => [
            [
                'id' => 'scope',
                'type' => SceneContract::TYPE_DIALOGUE,
                'prompt' => 'Ask for the operator goal and identify whether the request is read-only or action-oriented.',
                'choices' => [
                    ['id' => 'status', 'label' => 'Read status', 'next' => 'status'],
                    ['id' => 'action', 'label' => 'Prepare action', 'next' => 'review'],
                ],
                'fallback' => [
                    'prompt' => 'Ask for the goal in one sentence.',
                    'next' => 'scope',
                ],
            ],
            [
                'id' => 'status',
                'type' => SceneContract::TYPE_DECISION,
                'prompt' => 'Return configured status facts and ask whether review is needed.',
                'choices' => [
                    ['id' => 'review', 'label' => 'Open review', 'next' => 'review'],
                    ['id' => 'done', 'label' => 'Finish', 'next' => 'done'],
                ],
                'fallback' => [
                    'prompt' => 'Offer status summary or review.',
                    'next' => 'status',
                ],
            ],
            [
                'id' => 'review',
                'type' => SceneContract::TYPE_HANDOFF,
                'prompt' => '',
                'fallback' => [
                    'prompt' => 'Collect only the configured operator review fields.',
                    'next' => 'review',
                ],
                'handoff' => [
                    'target' => 'operator_review',
                    'reason' => 'Action requires explicit review before execution.',
                ],
            ],
            [
                'id' => 'done',
                'type' => SceneContract::TYPE_DIALOGUE,
                'prompt' => 'Close the scene with a short status summary.',
                'choices' => [
                    ['id' => 'restart', 'label' => 'Start another task', 'next' => 'scope'],
                ],
                'fallback' => [
                    'prompt' => 'Return to task scope.',
                    'next' => 'scope',
                ],
            ],
        ],
    ],
];
