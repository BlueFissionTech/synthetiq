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
| `digest` | SHA-256 digest of the candidate-v1 canonical envelope representation. A matching digest proves only byte-equivalent representation, not authority, freshness, idempotency, persistence, or execution. |
| `source` | Provider-neutral source kind, identity reference, scope, source status, observation time, and optional expiry. |
| `producer` | Package or adapter name and version that produced this representation. |
| `lineage` | Whether the record is raw or derived, plus integrity-addressable parent identities/digests and redaction-safe provenance references. |
| `claim` | Claim kind, a content reference, and optional normalized value. Provider request/response payloads are not embedded. |
| `trust` | Review, confidence, uncertainty, freshness, and contradiction metadata. `authority` is always `none`. |
| `review` | Review state and untrusted references to separate reviewer/decision records. A host must independently resolve and scope-bind those records. Review never changes evidence authority. |
| `outcomes` | Separate requested, host-accepted, persisted, and executed observations. Their references remain untrusted until independently resolved to a scope-bound host receipt. Unknown is represented as `null`, not `false` or zero. |
| `withdrawal` | Explicit evidence-withdrawal state and a reference to a scoped tombstone record. Source revocation is separate. Empty, failed, denied, partial, or later-successful retrieval does not create or clear withdrawal. |
| `risk_signals` | Structured, non-executable warnings such as forged authority or prompt-injection patterns. |
| `diagnostics` | Stable reasons for missing, stale, rejected, conflicting, out-of-scope, denied, or failed evidence. |

The fixture representation is an array with this shape:

```php
[
    'version' => 1,
    'evidence_id' => 'evidence:example',
    'digest' => 'sha256:...',
    'digest_canonicalization' => 'synthetiq-advisory-evidence-candidate-v1',
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
        'parent_digests' => [],
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

### Canonical representation and identity

For this design candidate, the digest input is the complete envelope except the
`digest` field itself. Object/map keys are sorted lexicographically at every
depth, list order is retained, strings are UTF-8 JSON strings without escaped
slashes or Unicode, and the resulting JSON bytes are hashed with SHA-256. The
canonicalization identifier is
`synthetiq-advisory-evidence-candidate-v1`; changing those rules requires a new
identifier and compatibility review. Fixtures calculate and verify real digests
rather than using integrity-looking placeholders.

`evidence_id` identifies one immutable evidence item and is stable across
observations and envelope projections. Normalization, summarization, or any
other derivation creates a new evidence identity and records every parent ID and
digest. A matching ID with a different raw item is an identity collision and
must be rejected; a matching digest does not imply the same lifecycle or host
decision.

### Status, freshness, and admission

Candidate source statuses are `available`, `unavailable`, `partial`, `failed`,
`denied`, and `revoked`. Candidate freshness values are `current`, `stale`,
`expired`, and `unknown`. These dimensions remain independent. Source
revocation describes the source; evidence withdrawal describes a separate,
explicit tombstone and is not inferred from source status.

When evaluating whether captured evidence may enter a tenant/principal context,
the most restrictive applicable result wins in this order: invalid structure or
digest; scope mismatch; confirmed withdrawal; unavailable/failed/denied/revoked
source; stale/expired/unknown freshness; unresolved contradiction or rejection;
then independently verified host receipt. Rejected material may be safely
captured or quarantined for diagnostics, but capture is not admission and has no
routing, capability, or execution effect.

Reviewer, decision, and host-accepted references are evidence strings until a
host independently resolves them, verifies their digest and issuer, and binds
them to the same tenant/principal scope. A self-authored `accepted` value is not
a host receipt.

### Tombstone projection boundary

A valid evidence-withdrawal tombstone must bind its version, stable tombstone
ID, target evidence ID and digest, tenant/principal scope, reason, effective
time, issuer/producer reference, monotonic ordering value, idempotency key,
optional superseded tombstone, and optional replacement evidence. Wrong-scope,
wrong-target, duplicate/reordered, or otherwise unverifiable tombstones are
diagnostic inputs and cannot withdraw evidence. A valid withdrawal remains in
effect after source recovery unless a later independently verified tombstone
explicitly supersedes it; recovery alone cannot resurrect evidence.

The portable persistence, ordering, and reusable tombstone record belong to
Chronicler. The candidate fixtures model only the fields SynthetIQ needs to
project withdrawal safely into conversational diagnostics and adapters; they do
not define or implement the durable Chronicler contract.

## Required invariants

1. `trust.authority` is always `none`, including after review.
2. Evidence content cannot add capabilities, permissions, approvals, policy
   exceptions, or executable instructions.
3. Review and host decisions are separate derived records referenced by this
   envelope; their references are untrusted until independently resolved and
   scope-bound, and they do not rewrite immutable raw evidence.
4. Requested, host-accepted, persisted, and executed outcomes are independent.
   A stable identity, digest, or accepted request is not an execution receipt.
5. Missing, failed, partial, denied, stale, revoked, rejected, conflicting, and
   out-of-scope states remain explicit diagnostics.
6. Unknown confidence and uncertainty remain `null`; they are not converted to
   numeric zero.
7. Withdrawal requires an explicit scoped tombstone whose target identity and
   digest validate. Source revocation, retrieval absence/failure, duplicate or
   reordered tombstones, and later source recovery neither create nor clear
   withdrawal.
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
- reviewed evidence that still retains `authority=none`;
- unverified self-authored host acceptance;
- wrong-scope, wrong-target, duplicate, and reordered tombstones;
- derivation from withdrawn evidence; and
- source recovery that cannot resurrect withdrawn evidence.

The expected results are data-only. They require no effects and cannot be used
as host execution receipts.

## Adoption gate

Before a runtime implementation is introduced, maintainers must confirm this
shape and its ownership boundary against Automata lifecycle semantics,
Chronicler provenance/lifecycle storage, Wise policy-injection behavior, and
Opus host scope and authorization. Tests must then prove default-untrusted and
non-executable behavior across the actual runtime adapters.
