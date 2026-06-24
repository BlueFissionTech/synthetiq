# Conversation State

`BlueFission\SynthetIQ\State\ConversationState` is the typed state store for
persona, tone, mood, task slots, session metadata, and per-turn summaries.

It is intentionally small and serializable. Hosts can persist the array returned
by `toArray()` and restore it with `ConversationState::fromArray()` without
needing a database adapter in the core package.

```php
use BlueFission\SynthetIQ\State\ConversationState;

$state = ConversationState::fromArray(require 'sample_configs/conversation_state.php');
$ai->setConversationState($state);

$response = $ai->processInputEnvelope('status');
```

Before each turn, SynthetIQ applies state to the Automata `Context` under keys
such as:

- `persona`, `persona_name`, and `persona_role`
- `tone` and `mood`
- `task_state` and `task_slots`
- `session_id`, `user_id`, and `memory_scope`
- `conversation_state`

After each completed turn, the state store records the turn count, last intent,
and last response. Response envelopes include the current state snapshot under
`state` so callers can persist or inspect it.

The state store does not own durable storage, permissions, or workflow policy.
Those remain host/runtime responsibilities.
