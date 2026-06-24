# Policy Filters And Audit Trail

SynthetIQ policy filters inspect inputs and outputs around the deterministic
conversation pipeline. Filters can allow content, deny content with an optional
replacement response, and attach metadata to audit records.

```php
use BlueFission\SynthetIQ\Policy\PolicyDecision;
use BlueFission\SynthetIQ\Policy\PolicyFilterInterface;

$ai->addPolicyFilter(new class implements PolicyFilterInterface {
    public function inspectInput(string $input, $context, array $meta = []): PolicyDecision
    {
        return PolicyDecision::allow('input_allowed');
    }

    public function inspectOutput(string $output, $context, array $meta = []): PolicyDecision
    {
        return PolicyDecision::allow('output_allowed');
    }
});
```

Response envelopes expose policy status and the structured audit trail:

```php
$envelope = $ai->processInputEnvelope('hello');
$audit = $envelope['audit'];
```

Audit events include policy decisions, intent scores, fallback triggers, memory
recall, and selected response metadata. Use `setAuditRedactor()` when audit
payloads need redaction before they are stored or surfaced.

Policy filters are deterministic package hooks. External policy storage,
review queues, approval workflow, retention rules, and access control remain
host responsibilities.
