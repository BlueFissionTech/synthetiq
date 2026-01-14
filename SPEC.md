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
- `Responses\Generator`: Template-based response rendering.
- `Responses\Selector`: Decision-tree selection with predictive scoring.
- `ConversationHistory`: Stores past input/response pairs.
- `Skills\*`: Optional Automata skills for specific behaviors.
- `Models\LearningModel`: Optional PHP-ML model (currently standalone).

### Data Flow

1. Input is interpreted by an Automata `IInterpreter`.
2. Intent classification uses `Matcher` and keyword criteria.
3. Templates are chosen for the intent label.
4. Response candidates are scored using a decision tree and heuristics.
5. The selected response is recorded in history and context.

## Interfaces and Configuration

- Routes are added via `SynthetIQ::addRoute($statement, $type, $to)`.
- Templates are simple text strings with optional `{{input}}` substitutions.
- Sample configuration is provided in `sample_configs/`.

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
