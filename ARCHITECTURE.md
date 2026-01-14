# SynthetIQ Architecture

## Purpose

SynthetIQ is a deterministic, low-cost conversational layer that pairs intent
routing with template responses and lightweight prediction. It is designed to
sit between rigid scripted flows and heavy generative systems, while leaning on
Automata and Develation for language, memory, and orchestration primitives.

## Current Pipeline

1. **Input normalization**
   - `BlueFission\Automata\Language\ContractionNormalizer` runs before parsing.
2. **Interpretation**
   - `BlueFission\Automata\Language\Interpreter` applies grammar/token rules.
3. **Intent classification**
   - `BlueFission\SynthetIQ\Intents\Classifier` uses Automata `Matcher` and
     `KeywordTopicAnalyzer`, with keyword fallback.
4. **Response generation**
   - `BlueFission\SynthetIQ\Responses\Generator` renders templates via
     `BlueFission\HTML\Template`.
5. **Response selection**
   - `BlueFission\SynthetIQ\Responses\Selector` scores candidates using a
     trigram predictor and heuristics.
6. **Learning fallback**
   - `BlueFission\SynthetIQ\Models\LearningModel` supplies a Markov-style
     fallback when no response is selected.
7. **History & context**
   - `ConversationHistory` stores input/response pairs. `Context` holds the
     last and current intent.

## Data Surfaces

- `sample_configs/dialogue.php` provides templates and intent keyword hints.
- `sample_configs/skills.php` registers Automata intents/skills.
- `sample_configs/intent_boosts.php` provides curated intent keyword boosts.
- `sample_configs/eval_cases.php` provides evaluator cases.

## Why It Does Not Match SmarterChild or Tay

SmarterChild and Tay used massive curated datasets, continuous user feedback,
and large-scale retrieval pipelines. SynthetIQ currently lacks:

- Large, diverse training corpora and retrieval pipelines.
- Multi-stage intent routing with confidence and fallbacks.
- Robust short-term memory and dialog state tracking.
- Dynamic persona or mood state for consistent voice.
- Spelling tolerance and fuzzy matching beyond basic similarity.
- Automated evaluation gates (accuracy, coherence, safety).

## Target Architecture (Non-Generative)

### Intent Routing

- Multi-stage router using Automata `Intelligence` + `DataGroup` to blend:
  - keyword matching,
  - similarity and edit-distance matching,
  - context-aware transitions,
  - task/domain specific strategies.
- Confidence scoring with escalation:
  - If confidence falls below threshold, route to a mini quantized LLM.
  - Capture LLM answers as training candidates (guarded).

### Short-Term Memory and Context

- Use Automata memory + ABS and Holoscene to:
  - store recent dialog episodes,
  - retrieve relevant episodes by similarity,
  - weight intents and responses based on memory recall.
- Use Language `Walker` to attach parsed entities and grammatical roles to the
  memory graph for richer context routing.

### Scripted Vibe Templates

- Expand template support for `{=...}` to run scripted conversation "vibes":
  - themed tone modifiers,
  - persona state toggles,
  - dynamic variable injection from context/memory.

### Spelling and Near-Match Tolerance

- Normalize and correct user input before classification:
  - edit-distance correction,
  - phonetic matching,
  - token substitution using domain synonym lists.

### Performance and Model Lifecycle

- Offline training with explicit model caching.
- Incremental model updates from new dialogue and approved sessions.
- Benchmarks for latency, memory, and throughput.

## Wise Integration

Wise should route unknown or low-confidence intents to a mini, quantized LLM,
while storing approved results as new training data. The router must:

- enforce confidence thresholds,
- apply safety and policy filters,
- log decisions for auditing and regression testing.

## Observability

- Add structured logs for:
  - intent scores,
  - selected routes,
  - memory retrieval hits,
  - fallback triggers.

