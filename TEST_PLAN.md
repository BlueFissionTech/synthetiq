# SynthetIQ Test Plan

This plan defines the test suite needed to support the roadmap. Features not
yet implemented are marked as "not implemented".

## Unit Tests

- Intent classifier returns stable labels for known inputs. (Implemented)
- Intent keyword boosts are registered and prioritized. (Implemented)
- Response generator renders template variables. (Implemented)
- Response selector never returns responses outside candidate set. (Implemented)
- LearningModel memory and Markov fallback. (Implemented)

## Integration Tests

- Interpreter + classifier + generator end-to-end. (Implemented)
- Intent routing with keyword boosts vs generic keywords. (Partial)
- Unknown intent fallback returns default templates. (Implemented)
- Template `{=...}` blocks execute with context variables. (Not implemented)
- Spelling correction pipeline integrates before classification. (Not implemented)

## Behavioral Simulation Tests

- Multi-turn conversation flow adheres to route transitions. (Not implemented)
- Context biasing prefers recent intent clusters. (Not implemented)
- Short-term memory recalls relevant episodes. (Partial)
- Persona/tone persistence across turns. (Not implemented)

## Memory and Context Tests

- Holoscene memory writes each exchange. (Partial)
- ABS memory similarity retrieves related inputs. (Partial)
- Language `Walker` attaches entity roles to memory entries. (Not implemented)

## Performance Tests

- Training time bounds for baseline dialogue set. (Implemented)
- Inference latency below target budget. (Not implemented)
- Memory usage growth under load. (Not implemented)
- Batch classification throughput benchmark. (Not implemented)

## Resilience and Safety Tests

- Unknown intent triggers LLM fallback only below confidence threshold. (Partial)
- LLM fallback responses are logged and gated. (Not implemented)
- Safety filter blocks disallowed intents and content. (Not implemented)

## Dataset and Evaluation Tests

- Intent evaluation suite accuracy threshold. (Implemented)
- Confusion matrix regression snapshot. (Not implemented)
- Cross-domain dataset coverage report. (Not implemented)

