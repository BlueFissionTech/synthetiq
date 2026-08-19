# Automata Capability Alignment

SynthetIQ owns deterministic conversational classification, response selection,
training candidates, scene/profile contracts, and package-level diagnostics.
Automata remains the upstream owner of intelligence primitives, agent runtime,
context, memory, goals, statements, feedback, tools, and orchestration.

## Dependency baseline

- DevElation is consumed from Packagist at `^1.3.42` and resolves to
  `v1.3.42`.
- Automata is consumed from Packagist at `^1.0.0-alpha.3` and resolves to
  `v1.0.0-alpha.3`.
- Chronicler is resolved transitively from Packagist through Automata at
  `^0.1.2-alpha`.
- SimpleClients is consumed directly from Packagist at `^0.1.0-alpha`.
- No package-specific VCS repository overrides are required.

## Capability matrix

| Upstream surface | SynthetIQ status | Package boundary |
| --- | --- | --- |
| `Context` | Current | Passed through classification and model APIs; host runtimes own session and execution state. |
| `Intent`, `Matcher`, analyzers | Current | SynthetIQ defines conversational intent configuration and delegates matching primitives upstream. |
| `EntityExtractor` | Current | Available to the classifier without duplicating extraction infrastructure. |
| `MarkovPredictor` | Current | Used by `LearningModel` for bounded local fallback generation. |
| `OrganizedCollection` | Current | Used for weighted response memory, starters, and fragments. |
| DevElation `Arr`, `Str`, `Val`, `Num` | Current | Preferred for package-owned normalization, validation, collection, and numeric operations. |
| Agent/persona orchestration | Intentionally out of scope | SynthetIQ publishes declarative profiles and handoff results; Automata executes agents. |
| Goals, statements, feedback, tools | Intentionally out of scope | These remain Automata runtime capabilities unless a reusable conversational contract is identified. |
| Storage-backed memory and history | Deferred | SynthetIQ accepts bounded references; persistence and lifecycle ownership remain external. |
| Provider/network execution | Intentionally out of scope | Provider adapters and transport are not bundled into deterministic conversational contracts. |

The profile/context handoff work tracked separately provides the reusable
contract discovered by this audit. No additional runtime implementation should
be added to SynthetIQ without a focused package-owned user story.

## Validation

```text
composer validate --strict
vendor/bin/phpunit --do-not-cache-result tests/Intents/ClassifierTest.php
vendor/bin/phpunit --do-not-cache-result tests/Models/LearningModelTest.php
vendor/bin/phpunit --do-not-cache-result
```
