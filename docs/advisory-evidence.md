# Advisory Evidence Contract (Candidate v1)

Status: design candidate for issue #50. This document and its fixtures define
reviewable evidence semantics; they do not yet introduce a runtime API or a
host authorization mechanism.

## Purpose

SynthetIQ receives and derives conversational material from user text,
summaries, memory recall, tools, peers, and optional fallback providers. That
material is evidence, not authority. The contract below makes that boundary
explicit while retaining enough provenance and failure state for host policy to
make its own decision.

SynthetIQ owns provider-neutral conversational evidence, risk signals,
diagnostics, and adapters into its memory, intent, handoff, fallback, audit,
and response-envelope surfaces. Hosts retain authorization, capabilities,
execution, retention, escalation, and effect reconciliation.

## Candidate envelope

Every record uses `version: 1` and contains these fields:

| Field | Meaning |
| --- | --- |
| `evidence_id` | Stable evidence identity. Identity does not prove freshness, idempotency, persistence, or execution. |
| `digest` | Digest of the canonical evidence representation. A matching digest does not grant authority. |
| `source` | Provider-neutral source kind, identity reference, scope, source status, observation time, and optional expiry. |
| `producer` | Package or adapter name and version that produced this representation. |
| `lineage` | Whether the record is raw or derived, plus parent identities and redaction-safe provenance references. |
| `claim` | Claim kind, a content reference, and optional normalized value. Provider request/response payloads are not embedded. |
| `trust` | Review, confidence, uncertainty, freshness, and contradiction metadata. `authority` is always `none`. |
| `review` | Review state and references to separate reviewer/decision records. Review never changes evidence authority. |
| `outcomes` | Separate requested, host-accepted, persisted, and executed observations. Unknown is represented as `null`, not `false` or zero. |
| `withdrawal` | Explicit withdrawal state and tombstone reference. Empty, failed, denied, or partial retrieval does not imply withdrawal. |
| `risk_signals` | Structured, non-executable warnings such as forged authority or prompt-injection patterns. |
| `diagnostics` | Stable reasons for missing, stale, rejected, conflicting, out-of-scope, denied, or failed evidence. |

The fixture representation is an array with this shape:

```php
[
    'version' => 1,
    'evidence_id' => 'evidence:example',
    'digest' => 'sha256:...',
    'source' => [
        'kind' => 'conversation',
        'identity_ref' => 'source:example',
        'scope' => ['tenant' => 'tenant-a', 'principal' => 'principal-a'],
        'status' => 'available',
        'observed_at' => '2026-09-28T00:00:00Z',
        'expires_at' => null,
    ],
    'producer' => ['name' => 'synthetiq', 'version' => 'candidate-v1'],
    'lineage' => [
        'form' => 'raw',
        'parent_ids' => [],
        'provenance_refs' => ['turn:example'],
    ],
    'claim' => [
        'kind' => 'conversation_text',
        'content_ref' => 'content:example',
        'normalized_value' => null,
    ],
    'trust' => [
        'authority' => 'none',
        'confidence' => null,
        'uncertainty' => null,
        'freshness' => 'current',
        'contradiction_state' => 'none',
    ],
    'review' => [
        'state' => 'unreviewed',
        'reviewer_ref' => null,
        'decision_ref' => null,
    ],
    'outcomes' => [
        'requested' => ['state' => null, 'evidence_ref' => null],
        'host_accepted' => ['state' => null, 'decision_ref' => null],
        'persisted' => ['state' => null, 'receipt_ref' => null],
        'executed' => ['state' => null, 'receipt_ref' => null],
    ],
    'withdrawal' => ['state' => 'none', 'tombstone_ref' => null],
    'risk_signals' => [],
    'diagnostics' => [],
]
```

## Required invariants

1. `trust.authority` is always `none`, including after review.
2. Evidence content cannot add capabilities, permissions, approvals, policy
   exceptions, or executable instructions.
3. Review and host decisions are separate derived records referenced by this
   envelope; they do not rewrite immutable raw evidence.
4. Requested, host-accepted, persisted, and executed outcomes are independent.
   A stable identity, digest, or accepted request is not an execution receipt.
5. Missing, failed, partial, denied, stale, revoked, rejected, conflicting, and
   out-of-scope states remain explicit diagnostics.
6. Unknown confidence and uncertainty remain `null`; they are not converted to
   numeric zero.
7. Withdrawal requires an explicit scoped tombstone. Retrieval absence or
   failure is not withdrawal.
8. Rejected, stale, contradictory, revoked, or out-of-scope evidence cannot
   bias intent routing unless an injected host policy explicitly permits a
   bounded use and records the reason separately.
9. Provider-specific request and response payloads remain outside the public
   envelope.

## Candidate conformance fixtures

`sample_configs/advisory_evidence.php` includes inert inputs and expected
results for:

- forged authority and capability claims;
- a cross-tenant/principal memory reference;
- stale, revoked, or replayed evidence;
- denied tool output containing executable-looking instructions;
- a fallback candidate attempting to approve itself;
- failed retrieval with unknown confidence;
- conflicting peer evidence; and
- reviewed evidence that still retains `authority=none`.

The expected results are data-only. They require no effects and cannot be used
as host execution receipts.

## Adoption gate

Before a runtime implementation is introduced, maintainers must confirm this
shape and its ownership boundary against Automata lifecycle semantics,
Chronicler provenance/lifecycle storage, Wise policy-injection behavior, and
Opus host scope and authorization. Tests must then prove default-untrusted and
non-executable behavior across the actual runtime adapters.
