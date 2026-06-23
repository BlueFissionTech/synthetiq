#!jenss

use @system, @io from system;
use @goal from intelligence.goal;
use @statement from intelligence.statement;
use @assessment, @feedback, @policy from intelligence.feedback;
use @naiveBayes from intelligence.strategy;

speak via @io: $default;

$samples
set $samples to [
    "hello",
    "good morning",
    "goodbye",
    "see you later",
    "system status",
    "are services healthy",
    "weather tomorrow",
    "forecast next week"
];

$labels
set $labels to [
    "greeting.intent",
    "greeting.intent",
    "goodbye.intent",
    "goodbye.intent",
    "status.intent",
    "status.intent",
    "weather.intent",
    "weather.intent"
];

@trainingGoal
set @trainingGoal to @goal: /make "Convert accepted dialogue corrections into reusable routing data";

@model
set @model to @naiveBayes;
@model: /train $samples, $labels;

$probe
set $probe to "goodbye for now";

$prediction
set $prediction to @model: /predict $probe;

$predictedIntent
set $predictedIntent to $prediction: $value;

$predictionConfidence
set $predictionConfidence to $prediction: $confidence;

@history
set @history to @feedback: /make;
@history: /positive "intent:greeting.intent", 1;
@history: /positive "intent:status.intent", 1;
@history: /correct "intent:unknown.intent", "intent:goodbye.intent", 1;

$goodbyeScore
set $goodbyeScore to @history: /score "intent:goodbye.intent";

$unknownScore
set $unknownScore to @history: /score "intent:unknown.intent";

$feedbackSummary
set $feedbackSummary to @history: /explain;

@evalGate
set @evalGate to @assessment: /make 1, 0.72, "intent_regression_gate";

$evalSummary
set $evalSummary to @evalGate: /explain;

@evalSignal
set @evalSignal to @statement: /make "evaluation", "reports", "router accuracy", "may", "regression";
@evalSignal: /confidence 0.72;
@evalSignal: /evidence ["eval_cases", "feedback_history", "model_cache"];

@reviewPolicy
set @reviewPolicy to @policy: /make 0.75, 3;

@reviewDecision
set @reviewDecision to @reviewPolicy: /decide @evalSignal, "regression", 0;

say "Goal: `@trainingGoal: $name`";
say "Probe: `$probe`";
say "Predicted intent: `$predictedIntent`";
say "Prediction confidence: `$predictionConfidence`";
say "Goodbye feedback: `$goodbyeScore`";
say "Unknown feedback: `$unknownScore`";
say "Feedback: `$feedbackSummary`";
say "Evaluation: `$evalSummary`";
say "Review: `@reviewDecision: /status`";
say "Reason: `@reviewDecision: /reason`";
