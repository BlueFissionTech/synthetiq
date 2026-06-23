#!jenss

use @system, @io from system;
use @json from develation.data;
use @domain from intelligence.domain;
use @goal from intelligence.goal;
use @statement from intelligence.statement;
use @naiveBayes from intelligence.strategy;
use @markov from intelligence.language;
use @policy from intelligence.feedback;

speak via @io: $default;

$catalogData
set $catalogData to @json: /parse "examples/jenss/fixtures/router-catalog.json";

@catalog
set @catalog to @domain: /make $catalogData;

$utterances
set $utterances to @catalog: /section "utterances";

$labels
set $labels to @catalog: /section "labels";

$templates
set $templates to @catalog: /section "templates";

$probe
set $probe to @catalog: /section "probe";

@routeGoal
set @routeGoal to @goal: /make "Keep dialogue routing bounded and explainable";

@router
set @router to @naiveBayes;
@router: /train $utterances, $labels;

$intentResult
set $intentResult to @router: /predict $probe;

$intentLabel
set $intentLabel to $intentResult: $value;

$intentConfidence
set $intentConfidence to $intentResult: $confidence;

for each $template in $templates,
    @markov: /addSentence $template;

$nextWord
set $nextWord to @markov: /predictNextWord "Weather";

$nextToken
set $nextToken to $nextWord: $value;

@intentSignal
set @intentSignal to @statement: /make "user input", "routes", $intentLabel, "may", "conversation";
@intentSignal: /source "synthetiq:jenSS-fixture";
@intentSignal: /confidence 0.67;
@intentSignal: /evidence ["probe", "utterances", "labels", "templates"];

@reviewPolicy
set @reviewPolicy to @policy: /make 0.7, 5;
@reviewPolicy: /sensitive "fallback";

@reviewDecision
set @reviewDecision to @reviewPolicy: /decide @intentSignal, "fallback", 0;

say "Scenario: `@catalog: /scenarioName`";
say "Goal: `@routeGoal: $name`";
say "Probe: `$probe`";
say "Predicted intent: `$intentLabel`";
say "Model confidence: `$intentConfidence`";
say "Next template token: `$nextToken`";
say "Review: `@reviewDecision: /status`";
say "Reason: `@reviewDecision: /reason`";
