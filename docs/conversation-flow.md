# Conversation Flow Graphs

`BlueFission\SynthetIQ\Flow\ConversationFlow` models deterministic multi-turn
flows with explicit state, allowed intents, transitions, fallback recovery, and
completion state.

```php
use BlueFission\SynthetIQ\Flow\ConversationFlow;

$flow = ConversationFlow::fromArray(require 'sample_configs/conversation_flow.php');
$ai->setConversationFlow($flow);
```

Each state can define:

- `prompt`: operator or authoring guidance for the state.
- `allowed_intents`: intent labels permitted while the state is active.
- `transitions`: selected-intent labels mapped to next state ids.
- `fallback_intent`: a recovery intent used when input scores do not match
  allowed intents.
- `fallback`: a state id used when a selected intent does not have a transition.
- `complete`: whether reaching the state completes the flow.

During a turn, SynthetIQ lets the active flow constrain route scores before the
intent is selected. After the response is chosen, the flow advances by selected
intent and writes diagnostics to the response envelope under `flow`.

Flows can be reset, completed, or abandoned with:

```php
$ai->resetConversationFlow();
$ai->completeConversationFlow();
$ai->abandonConversationFlow();
```

This flow surface owns deterministic state routing only. Host runtimes own
storage, long-running task policy, and external side effects.
