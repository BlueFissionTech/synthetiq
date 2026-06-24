# Local Model Fallback Training Candidates

SynthetIQ exposes an optional local-model fallback adapter without bundling a
model runtime. Hosts provide a `FallbackProviderInterface`; SynthetIQ handles
the fallback contract, candidate capture, and approval workflow.

## Enable

```php
use BlueFission\SynthetIQ\Fallback\LocalModelFallbackResponder;
use BlueFission\SynthetIQ\Fallback\TrainingCandidateStore;

$store = new TrainingCandidateStore();
$fallback = new LocalModelFallbackResponder($provider, $store, [
    'enabled' => true,
]);

$ai->setFallbackResponder($fallback);
```

Fallback remains disabled unless the responder is explicitly enabled.

## Candidate Capture

Each generated fallback answer captures:

- prompt
- response
- input
- reason
- confidence
- intent
- scores
- stage
- raw fallback metadata

The response envelope includes the captured candidate under
`fallback.candidate`.

## Review Workflow

```php
$pending = $store->pending();
$store->approve($pending[0]['id'], ['reviewer' => 'qa']);
$store->reject($pending[1]['id'], ['reason' => 'not useful']);
```

Approved candidates can then be exported by the host into route catalogs,
training fixtures, or review queues. Persistence and model execution remain
outside the default package runtime.
