# Procedural conversation acceptance

Run the provider-free acceptance suite:

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Acceptance
```

The original MIT-licensed fixture catalog in
`tests/Fixtures/procedural_conversations.php` separates training statements from
held-out inputs. It contains no imported corpus or customer data.

## What this proves

- Support: password reset, shipment tracking, refund guidance, and closing.
- Conversation: greetings, empathy, an imaginative reply, and farewell.
- Synthetic console: clearly simulated help, status, listing, and exit replies.
- An eight-turn `pwd`/`ls`/`cd`/`exit` transcript exercises existing conversation-flow
  state and context-dependent templates, entirely in memory. Completing the flow
  does not stop a host process or authorize further commands.
- Twenty-four held-out inputs replay on two fresh instances from JSON-round-tripped
  compiled route state. Exact response, classified intent, selected response route,
  turn count, and absence of fallback must agree.
- An untrained answer changes after real `RouteTrainer` training. The analyzer
  boundary supplies no precomputed intent labels or scores; the actual trained
  keyword-overlap strategy, response generator, and selector determine the answer.
  The control requires no learned intent/scores/response route before training;
  an untrained input-seed fragment is never counted as a correct answer.
- Known-answer tests install a throwing fallback responder. Any fallback invocation
  fails the test, even if it would return plausible prose. No provider client is
  configured, so a hosted model cannot create a false positive.
- Unknown-after-known and ambiguous requests have separate, explicitly local
  clarification tests. They are recovery cases, not trained-answer successes.
- A corrected greeting still reaches its trained route without fallback.
- A test host policy rejects destructive and mixed console requests before the
  interpreter boundary is called.

## Deliberate bounds

This is deterministic route training, not LLM fine-tuning or a benchmark of general
language understanding. Positive transcripts pin keyword-overlap routing, disable
the Naive Bayes ensemble and word predictor, and use one reply template per intent.
Spell correction is isolated in its own case. These choices make failures
attributable; they do not certify every default configuration, vocabulary,
paraphrase, locale, or alternative response catalog.

The interpreter records test inputs and performs no execution. SynthetIQ invokes
the supplied interpreter during input processing; it is **not an OS sandbox**.
Production hosts must enforce authorization, execution, state, and effect policy.
The example denial rule is a focused fixture, not a complete command-security
policy. Synthetic status and files are authored fiction, never host observations.

Fallback usage and uncertainty diagnostics must remain part of any future score.
Do not promote a clarification or later hosted-model answer into this suite's
trained-answer pass count.
