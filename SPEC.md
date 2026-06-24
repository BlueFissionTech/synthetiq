# SynthetIQ Specification

## Purpose

Provide a simple, flexible conversational engine for low-cost, consistent chat bots. The library targets naive but useful dialogue systems that combine intent routing, response templates, and light text prediction rather than full generative modeling.

## Scope

- Intent-driven conversation flow using Automata analyzers and matchers.
- Template response generation with minimal data requirements.
- Lightweight prediction and response selection.
- Context tracking and conversational history.
- Optional skills for common conversational tasks.

## Non-Goals

- Full generative language modeling or open-ended conversation.
- Heavy external infrastructure requirements.
- Real-time, multi-agent orchestration.
- End-to-end knowledge retrieval pipelines.

## Users and Use Cases

- Product teams building help or sales assistance bots.
- Teams needing low-cost chat for small talk or limited workflows.
- Integrations that require consistent, predictable output.

## Key Decisions and Intent

- Prefer deterministic-ish outputs using templates, with small variation.
- Use Automata language tools for interpretation and intent matching.
- Use a lightweight Markov predictor to suggest continuity without "creative" generation.
- Keep data structures simple to support fast iteration and low operating cost.

## Architecture Overview

### Components

- `SynthetIQ`: Orchestrates the flow (interpret -> classify -> generate -> select -> record).
- `Intents\Classifier`: Uses Automata matchers with a naive keyword fallback.
- `Intents\IntelligenceRouter`: Blends matcher, keyword overlap, and optional Naive Bayes strategy scores.
- `Language\SpellCorrector`: Provides optional vocabulary-driven edit-distance normalization.
- `Responses\Generator`: Template-based response rendering.
- `Responses\Selector`: Decision-tree selection with predictive scoring.
- `ConversationHistory`: Stores past input/response pairs.
- `Skills\*`: Optional Automata skills for specific behaviors.
- `Training\RouteTrainer`: Compiles, saves, validates, and applies route-state catalogs.
- `Fallback\FallbackResponderInterface`: Allows deterministic or optional low-confidence fallback behavior.
- `Models\LearningModel`: Optional PHP-ML model (currently standalone).

### Data Flow

1. Input is optionally normalized by `SpellCorrector`.
2. Input is interpreted by an Automata `IInterpreter`.
3. Intent classification uses `IntelligenceRouter`, Automata matchers, keyword criteria, and configured strategies.
4. Low-confidence or unknown intent paths can call a configured fallback responder.
5. Templates are chosen for the intent label.
6. Response candidates are scored using a decision tree and predictor diagnostics.
7. The selected response is recorded in history and context.
8. Callers can use `processInputEnvelope()` when structured diagnostics are required.

## Interfaces and Configuration

- Routes are added via `SynthetIQ::addRoute($statement, $type, $to)`.
- Templates are simple text strings with optional `{{input}}` substitutions.
- Sample configuration is provided in `sample_configs/`.
- Route catalogs can be compiled and applied through `RouteTrainer`.
- Response predictor diagnostics and response envelopes are public contracts for observability.
- Recalled memory episodes can influence routing through intent biases and can
  be reported in the response envelope after response selection.

### Memory Response Selection

SynthetIQ normalizes related memory entries into `memory.selection`, including
matched entries, selected response, selected response intent, recall metadata,
and bounded counts. Memory storage, scope isolation, and permission guards
remain adapter concerns.

## Quality Targets

- Flexibility: easy to add intents, routes, and templates without model training.
- Reliability: consistent output given the same inputs and context.
- Speed: fast classification and selection for interactive chat.
- Low cost: avoid heavy compute or external dependencies in the core path.

## Risks and Constraints

- Some skills rely on app-specific services or globals, which can hinder portability.
- Response selection uses randomness and heuristics, which can reduce determinism.
- The standalone learning model is not integrated into the primary flow.

## Testing Strategy (High Level)

- Focus on intent classification, routing, and response selection.
- Use deterministic predictors or seeded randomness for repeatable tests.
- Mock Automata components where needed to keep tests fast and reliable.
- Keep optional JenSS/Jenerator fixture validation opt-in through environment variables documented in `tests.md`.
