<?php

declare(strict_types=1);

// Original, repository-authored fixtures under the package MIT license.
// Training text and held-out input are separate; no customer or third-party corpus.
return [
    'support' => [
        'recovery' => 'I cannot answer that support question yet. Please choose password, shipment, or refund help.',
        'intents' => [
            'password' => [
                'training' => ['reset password', 'forgot password'],
                'response' => 'Use the password reset link. Never share your password.',
                'held_out' => ['please reset my password', 'forgot my password again'],
            ],
            'shipping' => [
                'training' => ['track shipment', 'shipping delivery'],
                'response' => 'Check the tracking page with your order reference.',
                'held_out' => ['please track this shipment', 'shipping delivery update'],
            ],
            'refund' => [
                'training' => ['refund request', 'return purchase'],
                'response' => 'I can explain the return policy; a refund still needs review.',
                'held_out' => ['request a refund please', 'return my purchase'],
            ],
            'closing' => [
                'training' => ['thanks support', 'thank assistance'],
                'response' => 'You are welcome. Keep your account details private.',
                'held_out' => ['thanks for the support', 'thank you for the assistance'],
            ],
        ],
    ],
    'conversation' => [
        'recovery' => 'I do not have a reliable answer yet. We can chat about your day or imagine a tiny world.',
        'intents' => [
            'greeting' => [
                'training' => ['hello friend', 'good morning'],
                'response' => 'Hello! What would you like to explore today?',
                'held_out' => ['hello my friend', 'good morning everyone'],
            ],
            'empathy' => [
                'training' => ['feeling frustrated', 'difficult day'],
                'response' => 'That sounds frustrating. Would you like to talk or take a small next step?',
                'held_out' => ['feeling really frustrated', 'a difficult day today'],
            ],
            'creative' => [
                'training' => ['imagine moon garden', 'invent tiny world'],
                'response' => 'Imagine a moon garden where each silver flower keeps a tiny sunrise.',
                'held_out' => ['imagine a moon garden', 'invent a tiny world'],
            ],
            'farewell' => [
                'training' => ['goodbye friend', 'see later'],
                'response' => 'Goodbye! May your next small adventure be a good one.',
                'held_out' => ['goodbye my friend', 'see you later'],
            ],
        ],
    ],
    'synthetic_repl' => [
        'recovery' => 'Simulation: unsupported request. Try help, status, or list; nothing was executed.',
        'intents' => [
            'help' => [
                'training' => ['show commands', 'command help'],
                'response' => 'Simulation only. Available: help, status, list, exit. No host commands are executed.',
                'held_out' => ['please show commands', 'command help please'],
            ],
            'status' => [
                'training' => ['system status', 'simulation status'],
                'response' => 'Simulation status: ready. This is not a measurement of the host.',
                'held_out' => ['show system status', 'simulation status please'],
            ],
            'list' => [
                'training' => ['list files', 'show directory'],
                'response' => 'Simulated files: notes.txt, projects/. No host filesystem was read.',
                'held_out' => ['list my files', 'show this directory'],
            ],
            'exit' => [
                'training' => ['exit simulation', 'quit session'],
                'response' => 'Simulation session finished. No host process was stopped.',
                'held_out' => ['exit this simulation', 'quit the session'],
            ],
        ],
    ],
];
