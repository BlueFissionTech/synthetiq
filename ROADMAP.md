# SynthetIQ Roadmap

Status legend: Implemented, Partial, Not implemented.

## Phase 0 - Stabilize (Now)

- Intent routing and response selection baseline. (Implemented)
- Trigram Markov selector for response consistency. (Implemented)
- Unknown intent fallback responses. (Implemented)
- Training progress indicator for large dialogue sets. (Implemented)
- Evaluator script for intent accuracy. (Implemented)
- Intent keyword boosts configuration. (Implemented)

## Phase 1 - Accuracy and Routing

- Multi-stage intent routing with confidence thresholds. (Partial)
- Context-aware intent biasing using `Context` history. (Partial)
- Keyword/phrase weighting tuned per domain. (Partial)
- Misspelling and near-match correction pipeline. (Not implemented)
- Multi-turn conversation flow graphs. (Not implemented)
- Data expansion and regression evaluation suite. (Not implemented)

## Phase 2 - Memory and Persona

- Short-term memory with Holoscene + ABS memory. (Partial)
- Language `Walker` integration for entity roles. (Not implemented)
- Conversation state store (persona, mood, tone, task state). (Not implemented)
- Scripted vibe templates using `{=...}` blocks. (Not implemented)
- Retrieval of relevant prior turns for response selection. (Not implemented)

## Phase 3 - Production Readiness

- Offline model training + cached inference bundles. (Partial)
- Multi-strategy benchmarking via Automata `BenchmarkService`. (Not implemented)
- LLM fallback (quantized) for unknown intents. (Partial)
- Safety and policy filters with audit logs. (Not implemented)
- Load testing and latency/throughput budgets. (Not implemented)

## Feature List

| Feature | Description | Status |
| --- | --- | --- |
| Intent classifier | Keyword + analyzer routing | Implemented |
| Response templates | Automata template rendering | Implemented |
| Trigram predictor | Response scoring | Implemented |
| Unknown intent bucket | Default responses | Implemented |
| Intent keyword boosts | Curated keywords per intent | Implemented |
| Scripted vibe templates | `{=...}` execution blocks | Not implemented |
| Spelling tolerance | Fuzzy correction + phonetics | Not implemented |
| Short-term memory | Holoscene + ABS memory | Partial |
| Context routing | Intent weighting by history | Partial |
| Strategy ensemble | Multi-strategy intent routing | Not implemented |
| LLM fallback | Mini quantized LLM for unknown intent | Partial |
| Safety filters | Policy gating and logging | Not implemented |

