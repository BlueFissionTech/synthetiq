# Conversation Profiles and Context Handoff

SynthetIQ profiles declare conversational identity, role, supported intents,
capabilities, policy metadata, and the context-reference categories a profile
may receive. They do not own agent orchestration, provider execution, sessions,
permissions, processes, or network access.

## Deterministic handoff

```php
use BlueFission\SynthetIQ\Handoff\ContextEnvelope;
use BlueFission\SynthetIQ\Handoff\ContextHandoff;
use BlueFission\SynthetIQ\Profiles\ConversationProfile;
use BlueFission\SynthetIQ\Profiles\ProfileRegistry;

$config = require 'sample_configs/conversation_profiles.php';
$profiles = [];
foreach ($config['profiles'] as $definition) {
    $profiles[] = ConversationProfile::fromArray($definition);
}

$registry = new ProfileRegistry($profiles);
$context = ContextEnvelope::fromArray($config['handoff']['context']);
$profile = $registry->selectFor(
    $context->currentIntent(),
    $config['handoff']['required_capabilities']
);

if ($profile !== null) {
    $result = (new ContextHandoff())->handoff(
        $profile,
        $context,
        $config['handoff']['required_capabilities']
    );
}
```

The result exposes `handoff_status`, `profile_id`, bounded `context`,
`diagnostics`, and a deterministic `output_id`. Status is one of `accepted`,
`rejected`, `clarification`, or `failure`.

The sample configuration also publishes stable `outcome_fixtures` for all four
statuses. They are side-effect-free and require no filesystem, process,
network, or provider capability.

## Boundary and redaction

Only entries in a profile's `context_permissions` pass into the accepted
context. Other `context_refs` are removed and reported as
`redacted_context_ref:<name>` diagnostics. Missing capabilities reject the
handoff without returning context. Missing current intent requests
clarification. Invalid target profiles fail without returning context.

The output ID is derived from the target profile, accepted status, and bounded
context. Diagnostic changes do not change the identity of an otherwise
identical accepted handoff, so resumed deterministic requests retain the same
ID.

Host-owned invocation identifiers, session enforcement, safety policy,
execution state, waiting/completion state, exit status, storage, process,
network, and provider capabilities stay outside this package.

## DevElation extension points

The contract carriers extend `BlueFission\Obj`, use DevElation primitives for
normalization and validation, dispatch behavioral process/success/failure
events, and expose these global filters and actions:

- Filters: `synthetiq.profile.input`, `synthetiq.profile.normalized`,
  `synthetiq.profile.selection.intent`,
  `synthetiq.handoff.context.input`,
  `synthetiq.handoff.context.normalized`, and
  `synthetiq.handoff.required_capabilities`.
- Actions: `synthetiq.profile.created`, `synthetiq.profile.registered`,
  `synthetiq.profile.selected`, `synthetiq.profile.selection_failed`,
  `synthetiq.handoff.<status>`, and `synthetiq.handoff.completed`.

Filters can customize package-owned data before selection or handoff. They do
not bypass profile validation, capability checks, or context-reference
redaction.
