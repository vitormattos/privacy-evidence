# LibreCode Privacy Evidence service go/no-go plan

Plan version: **0.1.0**

Status: **planning only**. This document does not authorize a public deployment, customer onboarding, payment collection or production data processing.

This plan implements issue #207 and depends on:
- `service-boundary.md` (#202);
- `public-scan-security.md` (#203);
- `offers-cost-pricing.md` (#204);
- `technology-transfer-evaluation.md` (#206).

## Governing rule

Progress through stages is monotonic only when exit evidence is recorded.

An implementation agent may prepare code or documentation explicitly allowed by the current stage, but may not infer approval to move to the next stage from technical readiness alone.

**Human approval is required at every transition that increases exposure to real users, real customer data, financial commitments or public Internet availability.**

## Roles

Roles may be held by one or more people, but approval responsibilities must remain explicit.

- **Research-core maintainer** — confirms engine/protocol/version boundary and scientific semantics.
- **Service owner** — owns product scope, operations and stage evidence.
- **Security reviewer** — reviews SSRF, isolation, egress, abuse controls and incident response.
- **Privacy/data reviewer** — reviews retention, submitted-domain/artifact handling and telemetry.
- **Commercial owner** — owns pricing, support/SLA assumptions and unit economics.
- **LibreCode authorized approver** — human governance approval for private/public/commercial transitions.

Automated agents may prepare evidence but are not substitutes for the required human approvers.

---

# Stage 0 — Specification complete

Purpose: planning baseline.

## Entry criteria

- productization epic exists as concept only;
- no production implementation authority is assumed.

## Exit criteria

All are complete:
- research/service boundary;
- technology-transfer data boundary;
- public-scan threat model/quotas/retention;
- offer/cost/pricing hypotheses;
- this staged plan.

## Metrics

No operational metrics required.

## Rollback

Not applicable; documentation may be revised through normal review.

## Human approval

No deployment approval occurs at Stage 0.

## Implementation issues allowed

Only:
- research-core fixes independently justified by research needs;
- test/benchmark work needed to measure service cost/safety;
- documentation/specification issues.

No hosted service implementation issue should be interpreted as deployment authority.

---

# Stage 1 — Research-core readiness

Purpose: prove the core is stable enough to be pinned by a service.

## Entry criteria

Stage 0 complete.

## Required evidence

- all core CI/quality gates green at the candidate pinned commit/release;
- public CLI/API path used by the service is documented;
- ResearchRun provenance records engine/protocol/schema/detector/profile configuration;
- private/link-local/metadata blocking and browser safety invariants are tested;
- bounded resource configuration exists;
- open-source license/citation metadata are present;
- known research-semantic gaps are documented;
- service will pin an immutable engine commit/release rather than track `main`.

## Exit criteria

The research-core maintainer records:
- candidate engine commit/release;
- protocol/schema versions;
- supported invocation boundary;
- known limitations;
- confirmation that service-specific code can remain outside the research core.

For public/commercial stages, final PoC acceptance (#87/#80) should be complete unless a human governance decision explicitly accepts a narrower experimental service scope.

## Metrics

- CI success rate on candidate release;
- deterministic test/reproduction status;
- resource telemetry availability;
- unresolved critical security defects: target **0**.

## Rollback conditions

Return to Stage 0/1 if:
- a service requirement would require silently changing measurement semantics;
- a critical security defect appears;
- the pinned release cannot produce required provenance.

## Human approval

Research-core maintainer.

## Implementation issues allowed

May open:
- stable service adapter/CLI invocation issue;
- provenance-contract tests;
- production-like benchmark harness;
- service repository skeleton **without deployment**.

---

# Stage 2 — Threat-model and cost-model readiness

Purpose: prove a service can be estimated and secured before exposing it to users.

## Entry criteria

Stage 1 exit recorded.

## Required evidence

From #203/#204/#206:
- SSRF/DNS-rebinding/redirect controls are implementable;
- browser isolation/egress policy has a concrete deployment design;
- queue, quota, cooldown and circuit-breaker semantics are selected;
- artifact privacy classes and TTL are selected;
- operational telemetry remains non-research by default;
- production-like benchmark measures CPU, memory, network, storage and browser-heavy tails;
- a cost-per-scan model exists by outcome class;
- support and expert-review labor assumptions are explicit.

## Exit criteria

A written implementation estimate exists for:
- service skeleton;
- worker isolation/network policy;
- queue/storage;
- telemetry/retention;
- abuse controls;
- expected monthly fixed cost;
- marginal scan cost ranges.

Security and privacy reviewers have no unresolved **critical** design blocker.

## Metrics

- estimated marginal cost per completed/partial/failed scan;
- p50/p95 worker wall time;
- p50/p95 memory;
- bytes acquired/retained per scan;
- browser escalation rate;
- estimated safe concurrency;
- projected abuse load.

## Rollback conditions

Stay at Stage 2 if:
- private-network egress cannot be independently blocked;
- cost tail is unbounded/unknown;
- retention/security design conflicts with research-core provenance;
- pricing hypotheses cannot plausibly cover variable cost.

## Human approval

Security reviewer + privacy/data reviewer + service owner.

## Implementation issues allowed

May open implementation issues for:
- non-public service API/UI skeleton;
- queue/orchestrator;
- isolated worker runtime;
- quotas/rate limits;
- artifact store/TTL;
- internal observability.

No Internet-public scan endpoint yet.

---

# Stage 3 — Internal LibreCode dogfood

Purpose: operate the service only for explicitly authorized internal users/targets.

## Entry criteria

Stage 2 exit approved.

## Scope

- access restricted to LibreCode-controlled identities;
- no anonymous public access;
- no payment;
- no customer promises/SLA;
- targets are public resources selected for internal testing;
- no private/authenticated target scanning;
- service layer pins exact core version.

## Required evidence during dogfood

- successful/partial/failed scan distribution;
- p50/p95 wall time and resource use;
- per-domain concurrency behavior;
- quota/circuit-breaker operation;
- SSRF/redirect/DNS-rebinding negative tests;
- artifact deletion/TTL verification;
- operator support burden;
- user comprehension of unknown/unavailable vs absent;
- operational incident/deviation log.

## Exit criteria

A dogfood report shows:
- no critical security/privacy incident;
- bounded resource cost;
- repeatable deployment/rollback;
- result wording is not being interpreted as legal certification in routine use;
- operational runbook and incident response are usable;
- all critical/high defects affecting pilot safety are resolved or explicitly accepted by humans.

## Metrics

- cost per scan/outcome;
- completion/partial/failure rate;
- p95 latency;
- support minutes per test user;
- artifact-deletion success;
- security-control test pass rate;
- operator interventions per scan.

## Rollback conditions

Immediately stop intake if:
- private/internal network access is observed;
- artifact retention violates policy;
- unbounded worker/queue behavior occurs;
- repeated semantic misrepresentation appears in UX;
- a critical vulnerability is identified.

Rollback = disable enqueueing, terminate workers if necessary, preserve only minimal incident evidence, and return to Stage 2.

## Human approval

Service owner + security reviewer + LibreCode authorized approver.

## Implementation issues allowed

May open:
- internal deployment;
- operator dashboard;
- private dogfood UX;
- internal load test;
- remediation issues from dogfood.

No external pilot/public deployment issue without Stage 3 exit approval.

---

# Stage 4 — Limited private pilot

Purpose: test real external usefulness with a small invited group under controlled conditions.

## Entry criteria

Stage 3 exit approved.

Before inviting external participants:
- privacy notice/data-handling terms exist;
- support scope is explicit;
- pilot target count/user count and duration are fixed;
- no payment unless separately approved;
- incident/contact path exists;
- telemetry fields/retention match #206;
- any human-participant research use has a separate approved research protocol/consent path.

## Recommended initial ceiling

- maximum **5 pilot organizations**;
- maximum **50 monitored public domains** total;
- fixed pilot window (for example 30–60 days);
- no unrestricted API/bulk access.

These are governance ceilings, not commercial plan definitions.

## Exit criteria

Pilot report records:
- usefulness/understandability evidence;
- actual variable cost;
- support effort;
- abuse/security events;
- scan outcome distribution;
- retention/privacy compliance;
- demand for each candidate offer;
- unresolved legal/product assumptions.

No critical security/privacy incidents remain unresolved.

## Metrics

- active pilot organizations;
- repeated usage;
- qualified feature/offer demand;
- cost/domain/month;
- support minutes/account;
- result/report use;
- retention/churn intent;
- security/privacy incident count;
- compliance-wording misunderstanding incidents.

## Rollback conditions

Suspend pilot if:
- a critical vulnerability/SSRF isolation failure appears;
- cost exceeds declared pilot budget;
- data handling deviates from policy;
- customers materially misunderstand outputs as certification despite corrections;
- operator workload is not supportable.

## Human approval

Service owner + security reviewer + privacy/data reviewer + LibreCode authorized approver.

## Implementation issues allowed

May open:
- invited-user onboarding;
- pilot account management;
- bounded scheduling/monitoring;
- pilot report/export refinements;
- remediation from pilot evidence.

Public anonymous access remains forbidden.

---

# Stage 5 — Public free scan

Purpose: expose only the bounded free single-site path after safety/cost/privacy evidence exists.

## Entry criteria

Stage 4 exit approved plus:
- public threat model re-review;
- CAPTCHA/abuse provider selected;
- IP/domain/global circuit breakers deployed/tested;
- public privacy notice/terms/status/contact paths ready;
- production monitoring/alerting/incident response ready;
- anonymous retention/TTL verified;
- fixed operating budget and shutdown threshold approved;
- public-facing wording reviewed to avoid legal-compliance certification.

## Exit criteria

Stage 5 is successful only after an observation window demonstrates:
- abuse remains within controls;
- cost per useful scan is bounded;
- infrastructure is stable at real public load;
- qualified conversion/demand can be measured;
- privacy/retention controls remain effective.

Public availability itself is not success.

## Metrics

- accepted/completed/partial scans;
- CAPTCHA/challenge rate;
- rate-limit/abuse rate;
- cost per accepted/completed scan;
- queue saturation/p95 latency;
- CTA/account/contact conversion;
- qualified-domain-owner/practitioner rate where measurable without invasive tracking;
- incident count;
- artifact deletion success.

## Rollback conditions

Automatically or manually disable new public scans when:
- fixed daily/monthly cost guardrail is exceeded;
- queue/capacity circuit breaker trips;
- abuse bypass is observed;
- critical security/privacy issue appears;
- required monitoring is unavailable.

Static informational pages may remain online while scan intake is disabled.

## Human approval

Security reviewer + privacy/data reviewer + service/commercial owner + LibreCode authorized approver.

This approval is a genuine human governance gate.

## Implementation issues allowed

Only after approval may open issues for:
- public anonymous endpoint;
- public CAPTCHA/rate-limit integration;
- production status/monitoring;
- public launch operations.

No payment/recurring billing yet unless Stage 6 is separately approved.

---

# Stage 6 — Paid / recurring offers

Purpose: monetize only offers with measured demand and sustainable unit economics.

## Entry criteria

Stage 5 evidence plus:
- at least one candidate paid offer has measurable demand from pilot/public use;
- production-like unit economics include infrastructure, payment fees, support and human review where applicable;
- billing/refund/tax/accounting responsibilities are approved;
- support/SLA scope is approved;
- retention/data-processing terms match the paid feature;
- plan limits remain within safety ceilings;
- no paid feature weakens research-core security invariants.

## Offer-specific gates

### Detailed paid report

Require:
- paid intent/conversion experiment;
- stable report semantics;
- bounded support/refund burden.

### Recurring monitoring

Require:
- evidence that repeated scans produce useful change events;
- stable cost/domain/month;
- retention/churn evidence from pilot.

### Bulk/API

Require:
- design-partner integration;
- measured concurrency/cost;
- tenant/API security review;
- no arbitrary webhook callback until outbound-callback SSRF design is approved.

### Expert review

Require:
- measured review minutes;
- explicit scope;
- reviewer qualification/QA process;
- wording that separates expert interpretation from automated legal certification.

## Exit criteria

A paid offer may remain enabled only while:
- contribution economics remain within approved bounds;
- support/SLA is sustainable;
- security/privacy controls remain effective;
- customer wording stays inside the declared claim boundary;
- demand is real rather than inferred from free traffic alone.

## Metrics

- paid conversion;
- MRR/one-off revenue by offer;
- gross contribution after variable cost/support/review;
- churn/retention;
- support minutes/account;
- refunds/disputes;
- scan volume/cost tails;
- incident count.

## Rollback conditions

Pause new sales/renewals for an offer if:
- unit economics become materially negative outside an approved experiment budget;
- support load breaches declared capacity;
- a security/privacy issue affects the offer;
- product claims drift toward unsupported compliance certification;
- the offer requires weakening measurement/security semantics.

## Human approval

Commercial owner + service owner + security/privacy approvers as applicable + LibreCode authorized approver.

Financial expenditure, payment-provider setup and public commercial launch remain human-only gates.

## Implementation issues allowed

Only after the relevant offer gate is approved:
- billing/payment integration;
- paid plan/entitlement enforcement;
- recurring scheduler at production scale;
- commercial SLA/support tooling;
- paid expert-review workflow.

---

# Cross-stage evidence record

Every stage transition must create a durable decision record containing:
- stage/version;
- date;
- exact service/core versions;
- entry criteria checked;
- evidence/metrics reviewed;
- open risks;
- approved scope;
- rollback trigger/owner;
- human approver names/roles;
- next-stage permission;
- implementation issues explicitly authorized.

Absence of this record means **no-go**.

## Autonomous-agent rule

An autonomous agent may:
- prepare specifications;
- implement tests/benchmarks;
- implement code already authorized within the current stage;
- collect non-human technical evidence;
- draft a stage decision record.

An autonomous agent may **not**:
- approve its own stage transition;
- expose a new public endpoint;
- recruit/use customer data as research data;
- accept legal/privacy risk;
- authorize financial expenditure;
- enable payment collection;
- interpret a green CI run as public-deployment approval.

## Current state

As of this plan's creation:
- Stage 0 specification artifacts are complete;
- no public/service implementation is authorized by #184/#207;
- movement to Stage 1/2 implementation preparation still requires a separately opened implementation scope and the human approvals defined above;
- the scientific critical path remains independent of this product concept.

## Acceptance check

For every required stage, this plan defines:
- entry criteria: **yes**;
- exit criteria: **yes**;
- metrics: **yes**;
- rollback conditions: **yes**;
- owner/human approvals: **yes**;
- implementation issues that may be opened: **yes**.

The plan explicitly prevents technical completion from being interpreted as permission to deploy.
