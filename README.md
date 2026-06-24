# SynthetIQ

SynthetIQ is a lightweight conversational library for building simple, low-cost chat experiences. It sits between rigid, scripted workflows and heavy generative systems by combining intent routing, template responses, and small-scale text prediction. The goal is flexible, consistent conversation for help systems, sales assistance, and low-risk dialogue where reliability matters more than creativity.

## Design Intent

- Keep responses predictable and low-cost by using templates and intent routing.
- Allow lightweight variation and continuity with a small Markov predictor.
- Favor consistent, repeatable behavior over unconstrained generation.
- Lean on BlueFission Develation and Automata objects for language, decision, and context utilities.

## How It Works

1. Input is interpreted by an Automata language interpreter.
2. Intents are classified via Automata analyzers and keyword matching.
3. Responses are generated from templates attached to those intents.
4. A decision tree and trigram Markov predictor score and select a response.
5. Conversation history and context update for follow-up turns.
6. Optional memory and fallback hooks can bias intent selection or route to a
   low-confidence fallback.
7. Optional spell correction can normalize near-miss tokens before routing.

## Install

SynthetIQ targets PHP 8.2 or newer.

```bash
composer install
```

## Quick Start

```php
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\SynthetIQ\Training\RouteTrainer;
use BlueFission\Automata\Language\{Interpreter, Grammar, StemmerLemmatizer, Documenter, Walker};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

$dialogue = require 'sample_configs/dialogue.php';
$intentBoosts = require 'sample_configs/intent_boosts.php';
$grammar = require 'sample_configs/grammar.php';
$tokens = require 'sample_configs/tokens.php';
$documenter = require 'sample_configs/documenter.php';

$interpreter = new Interpreter(
    new Grammar(new StemmerLemmatizer(), $grammar['rules'], $grammar['commands'], $tokens),
    $documenter,
    new Walker()
);

$analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, 'models/ml/');

$ai = new SynthetIQ($interpreter, $analyzer);

RouteTrainer::train($ai, $dialogue, $intentBoosts);

$result = $ai->processInputEnvelope('hello');
echo $result['response'];
```

See `example.php` for a CLI and browser demo.

## Examples

- `examples/support.php` provides the shared sample bootstrap used by the examples.
- `example.php` runs a small browser and CLI demo that returns response envelopes.
- `examples/cli.php` runs a CLI-only loop with the sample configs and current route trainer.
- `examples/minimal.php` shows a small custom routing setup for quick experiments.
- `examples/batch.php` runs a fixed number of inputs from `sample_configs/statements.php`.
- `examples/sequence.php` runs curated multi-turn batches and reports selected intents.
- `examples/envelope.php` prints the structured response envelope, fallback state, correction metadata, and predictor diagnostics.
- `examples/evaluator.php` runs an intent accuracy report through the current envelope API.
- `examples/route_state.php` compiles, saves, loads, verifies, and applies cached route state.
- `examples/jenss/` contains optional JenSS stress fixtures for declarative route catalogs and feedback gates.

Useful smoke commands:

```bash
php example.php hello
php examples/minimal.php hello
php examples/envelope.php hello
php examples/evaluator.php --no-progress
php examples/route_state.php --write --state=models/routes/synthetiq_routes.json --apply --probe=hello
php benchmarks/selection.php 1000 50 5
```

## Client Configuration

Third-party clients for news/weather/location are configured in `sample_configs/clients.php`.
These rely on the Composer package `bluefission/simpleclients`.
External weather and news clients are disabled by default in the sample config so clean installs do not require credentials or app globals.

Use `BlueFission\SynthetIQ\Clients\ClientResolver` to wire optional clients explicitly:

```php
use BlueFission\SynthetIQ\Clients\ClientResolver;
use BlueFission\SynthetIQ\Clients\WeatherClientInterface;

$resolver = ClientResolver::make(require 'sample_configs/clients.php');
$weatherClient = $resolver->client(WeatherClientInterface::class);
```

The sample config includes null clients for clean installs and lists the SimpleClients bindings that can be enabled by changing the `enabled` and `bindings` entries. A local fake-client example is available:

```bash
php examples/optional_clients.php
```

## Core Components

- `BlueFission\SynthetIQ\SynthetIQ`: orchestrates interpretation, intent classification, response generation, and selection.
- `BlueFission\SynthetIQ\Intents\IntelligenceRouter`: combines matcher, keyword overlap, and optional Naive Bayes strategies.
- `BlueFission\SynthetIQ\Intents\Classifier`: provides baseline intent matching and fallback keyword checks.
- `BlueFission\SynthetIQ\Responses\Generator`: renders template-based responses.
- `BlueFission\SynthetIQ\Responses\Selector`: selects responses using a decision tree and prediction heuristics.
- `BlueFission\SynthetIQ\ConversationHistory`: stores input/response pairs.
- `BlueFission\SynthetIQ\Skills\*`: optional Automata skills for common responses.
- `BlueFission\SynthetIQ\Memory\*`: pluggable short-term memory adapters.
- `BlueFission\SynthetIQ\Fallback\*`: low-confidence fallback responders.

## Routing and Templates

Routes are built by registering statements for an intent label. Each statement becomes:

- an intent keyword hint,
- a response template, and
- training data for the trigram predictor.

This keeps behavior simple, repeatable, and easy to customize.

`RouteTrainer` centralizes route catalog registration, boost keyword handling,
progress events, stable cache-key generation, and compiled route-state
serialization:

```php
use BlueFission\SynthetIQ\Training\RouteTrainer;

$state = RouteTrainer::compile($dialogue, $intentBoosts, [
    'grammar' => $grammar,
    'tokens' => $tokens,
]);

RouteTrainer::saveState($state, __DIR__ . '/models/routes.json');

if (RouteTrainer::stateMatches($state, $dialogue, $intentBoosts, ['grammar' => $grammar, 'tokens' => $tokens])) {
    RouteTrainer::apply($ai, $state);
}
```

The route-state example can be used as a non-interactive rollout smoke command:

```bash
php examples/route_state.php --write --state=models/routes/synthetiq_routes.json
php examples/route_state.php --state=models/routes/synthetiq_routes.json
php examples/route_state.php --state=models/routes/synthetiq_routes.json --apply --probe=hello
```

## Intent Routing Strategies

`SynthetIQ` uses `IntelligenceRouter` by default. The router trains lightweight
strategies from registered route keywords, combines their scores, and exposes
diagnostics for review:

```php
$ai = new SynthetIQ($interpreter, $analyzer, null, null, null, null, [
    'strategy_weights' => [
        'matcher' => 1.0,
        'keyword_overlap' => 0.75,
        'naive_bayes' => 0.5,
    ],
    'strategy_thresholds' => [
        'matcher' => 0.2,
        'keyword_overlap' => 0.1,
    ],
]);
```

For direct router use:

```php
use BlueFission\SynthetIQ\Intents\IntelligenceRouter;

$router = new IntelligenceRouter($analyzer, null, [
    'enable_naive_bayes' => false,
]);

$scores = $router->score('hello there', $context);
$diagnostics = $router->lastDiagnostics();
```

## Response Predictor Diagnostics

Response selection uses a bounded trigram predictor when available. The public
diagnostic contract reports whether that predictor is available, disabled,
unavailable, or failed, and whether response selection had to fall back to a
candidate response:

```php
$diagnostics = $ai->responsePredictorDiagnostics();
```

Use `setResponsePredictor(null)` to disable predictor-assisted selection while
keeping template selection compatible. Custom predictors can expose
`predictNextWords`, `predictNextWord`, or `predictBeginning`.

## Response Envelope

`processInput(string)` still returns the selected response string. Use
`processInputEnvelope(string)` when callers need structured diagnostics for a
turn:

```php
$result = $ai->processInputEnvelope('hello');

echo $result['response'];
$intent = $result['intent']['label'];
$predictor = $result['predictor']['status'];
```

The envelope includes the response text, raw and normalized input, selected
intent label, confidence, score map, fallback state, memory recall summary,
correction metadata, and response predictor diagnostics.

## Notes and Constraints

- This library is not a generative model. It predicts and selects from known statements.
- Some skills (weather/news/status) depend on external services or app-specific globals and should be wired explicitly or excluded in lightweight deployments.
- `src/Models/LearningModel.php` is optional and not yet integrated with `SynthetIQ`.
- Generated cache and model artifacts belong under `models/` and should not be committed.
- Spell correction is lightweight and vocabulary-driven. You can disable it with:

```php
$ai->enableSpellCorrection(false);
```

## Testing

See `tests.md` for the full clean-install checklist, focused suites, optional
fixture environment variables, and generated artifact policy.

```bash
vendor/bin/phpunit --do-not-cache-result
```

Optional JenSS fixture smoke tests require a local Jenerator autoload path:

```bash
SYNTHETIQ_JENERATOR_AUTOLOAD=/path/to/jenerator/vendor/autoload.php vendor/bin/phpunit --do-not-cache-result tests/Jenss
```

## Benchmarking

```bash
php benchmarks/selection.php 1000 50 5
```

The current benchmark is a local smoke command, not a hard CI budget gate.

## License

MIT
