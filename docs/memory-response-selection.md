# Memory Response Selection

SynthetIQ memory recall contributes response-selection context in addition to
intent biasing. Memory adapters still return `MemoryRecall`, so existing
adapters remain compatible.

## Runtime Contract

- `MemoryRecall::intentBiases()` can bias the intent score map before routing.
- `MemoryRecall::related()` can include array entries or entries with an
  Automata `Context` under `context`.
- Recalled entries may expose `input`, `response`, `intent_label`, `scope`,
  `user_id`, `session_id`, `timestamp`, and `similarity`.
- SynthetIQ normalizes recalled entries into `memory.selection` in the response
  envelope after a response is selected.

The selector reports:

- `selected_response`
- `selected_intent`
- `related_count`
- `matched_count`
- `matches`
- `intentBiases`
- `meta`

## Scope And Permissions

Memory adapters are responsible for storage, scope, and permission decisions.
The Holoscene adapter supports `default_scope` and a `permission_guard` option.
Denied recall returns an empty `MemoryRecall`, which keeps the response envelope
selection context empty.

## Limits

Use adapter options such as `max_related` and `similarity_threshold` to keep
recall bounded. SynthetIQ does not store memory externally by itself and does
not bypass adapter permissions.
