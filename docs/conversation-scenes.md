# Conversation Scene Contracts

SynthetIQ scene contracts describe deterministic conversation paths before a
runtime flow engine executes them. They are package-owned data shapes for
authored scenes, choices, fallback prompts, voice guidance, public-safety rules,
and handoff metadata.

The contract is intentionally array-based so it can be authored by templates,
configuration files, or upstream scripting tools without requiring a runtime
dependency.

## Scene Shape

```php
[
    'version' => 1,
    'id' => 'proof_walkthrough',
    'title' => 'Proof Walkthrough',
    'summary' => 'Guides a visitor through a deterministic proof path.',
    'voice_policy' => [
        'tone' => 'calm, concise, and factual',
        'allowed' => ['summarize known proof steps'],
        'avoid' => ['inventing unavailable proof evidence'],
    ],
    'public_safety' => [
        'constraints' => ['do not collect secrets or payment data'],
        'escalation' => [
            'trigger' => 'unsupported commitment',
            'handoff' => 'consultation_request',
        ],
    ],
    'handoff' => [
        'consultation_request' => [
            'label' => 'Consultation request',
            'required_fields' => ['name', 'organization', 'contact', 'goal'],
        ],
    ],
    'states' => [
        [
            'id' => 'intro',
            'type' => 'dialogue',
            'prompt' => 'Ask which branch the visitor wants to inspect.',
            'choices' => [
                ['id' => 'architecture', 'label' => 'Architecture', 'next' => 'architecture'],
            ],
            'fallback' => [
                'prompt' => 'Offer the available branches again.',
                'next' => 'intro',
            ],
        ],
    ],
]
```

## State Types

- `dialogue`: speaks a configured prompt and may offer choices.
- `decision`: presents explicit choices and routes to the next state.
- `handoff`: stops the scene and emits handoff metadata for the host runtime.

## Validation

Use `BlueFission\SynthetIQ\Scenes\SceneContract` to validate or normalize a
scene definition:

```php
use BlueFission\SynthetIQ\Scenes\SceneContract;

$scene = require 'sample_configs/conversation_scenes.php';
$errors = SceneContract::validate($scene['proof_walkthrough']);

if (SceneContract::isValid($scene['proof_walkthrough'])) {
    $normalized = SceneContract::normalize($scene['proof_walkthrough']);
}
```

Validation checks required scene ids, voice policy, public-safety constraints,
state prompts, fallback data, decision choices, handoff metadata, and transition
targets.

## Boundaries

- SynthetIQ owns the deterministic scene contract, validation rules, and sample
  scene data.
- Automata remains the upstream orchestration and context/memory surface when a
  host runtime executes a scene.
- Vibe-authored artifacts can generate scene definitions, but this contract does
  not require a scripting runtime.
- Host applications own storage, review workflow, transport, permissions, and
  actual handoff execution.
