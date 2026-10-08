# Technology-transfer evaluation plan

Plan version: **0.1.0**

Status: **concept only**. This document does not authorize deployment, customer data collection or a new research study.

This plan implements issue #206 and depends on the research/service boundary in `service-boundary.md`.

## Purpose

A future LibreCode service may create operational evidence about whether Privacy Evidence is useful outside the original IPB research context. That operational use must not be converted retrospectively into research data by convenience.

The default rule is:

> **Service telemetry is operational data unless an explicit, separately approved research protocol says otherwise.**

Production use can motivate a future study. It does not automatically become one.

## Separation of operational metrics and research variables

### Operational metrics

A service may need the following for reliability, capacity and abuse control:
- job identifier;
- service/engine/protocol/profile version;
- submission and completion timestamps;
- coarse job status and failure class;
- pages/documents/requests processed;
- wall-clock duration;
- CPU/memory/byte-budget counters where available;
- queue latency;
- artifact/result retention state;
- rate-limit/quota decisions;
- coarse billing/plan identifier where applicable.

Operational metrics should be retained only for a declared operational purpose and period.

They must not be interpreted as research observations simply because they are available.

### Candidate research variables

A future technology-transfer study could deliberately define variables such as:
- task completion or abandonment under a declared service workflow;
- proportion of submitted resources that become measurable;
- measurement attrition by service-use context;
- human-review demand and deferral frequency;
- reproducibility of service-originated ResearchRuns;
- marginal compute/storage/review cost per completed unit;
- perceived usefulness or decision impact from separately consented participant responses;
- protocol adaptations required for a new population or organizational context.

A research variable exists only when its construct, unit, source, collection rule, purpose and retention policy are specified in the new protocol.

## Privacy-preserving telemetry principles

Future service design should prefer:
- aggregate counters over full event histories when the aggregate is sufficient;
- pseudonymous random job identifiers rather than account/email identifiers in research exports;
- coarse failure categories rather than raw exception bodies containing URLs or page content;
- domain/resource values separated from user/account identity;
- short retention for raw submitted URLs and collected artifacts unless a declared service purpose requires longer retention;
- hashes/provenance for restricted artifacts when redistribution is unnecessary;
- explicit deletion/retention behavior for customer-submitted material;
- no third-party analytics requirement for the research instrument itself.

IP addresses, account identifiers, billing data, support messages and free-text feedback are not research variables by default.

## Human and user data gate

A new ethics/privacy/data-policy review is required before a research protocol intentionally collects or reuses:
- identifiable user/account information;
- IP addresses beyond transient security processing;
- support messages or free text;
- interviews, surveys or usability responses;
- customer organization metadata not already public;
- billing/payment information;
- private/non-public target URLs or artifacts;
- behavioral event streams tied to an identifiable person.

The review must define lawful/ethical basis, consent where applicable, minimization, access control, retention, publication rules and withdrawal/deletion handling where relevant.

The primary IPB study is not amended by future service telemetry.

## Candidate future research questions

These are hypotheses for separate studies, not additions to the current first-paper RQs.

### TTRQ1 — Operational applicability

Can the versioned Privacy Evidence protocol complete its intended measurement workflow on service-originated public resources without changing the core measurement semantics?

### TTRQ2 — Usefulness

For an explicitly recruited participant population, which service outputs are understandable and useful for the declared decision task?

This question requires human-participant protocol/consent before data collection.

### TTRQ3 — Cost and scalability

What compute, storage and optional human-review resources are consumed per completed measurement unit under declared service budgets?

### TTRQ4 — Transfer-induced protocol pressure

Which service-use contexts require protocol adaptation, and do those adaptations preserve or redefine the constructs used by the research core?

### TTRQ5 — Adoption/funnel behavior

Which service stages are reached or abandoned under a declared user journey?

This is product research unless a separate scientific protocol explicitly frames it as a research question.

## Provenance requirements for service executions used in research

A service-originated execution can enter a future research dataset only when it can be bound to:
- exact Privacy Evidence Git/release identifier;
- engine version;
- protocol version;
- schema version;
- detector versions;
- regulatory-profile versions where used;
- browser/runtime versions where used;
- acquisition configuration and budgets;
- source/input snapshot or stable hash;
- ResearchRun identifier;
- artifact hashes or approved preserved artifacts;
- service-layer version/configuration relevant to the observation;
- declared research-study identifier and consent/ethics state where human data is involved.

If these fields cannot be reconstructed, the execution can remain operational evidence but is not eligible for a reproducibility claim.

## Research/service data boundary

A future service should maintain three logical classes:

1. **Operational service data** — required to provide, secure, meter and troubleshoot the service.
2. **Research-core artifacts** — measurement artifacts and provenance produced by the versioned engine.
3. **Approved research dataset** — a deliberate export derived from classes 1/2 under a separately versioned research protocol and data-policy decision.

There must be no automatic database view or export that silently promotes class 1 into class 3.

## Promotion procedure for a future study

Before service data is used scientifically:

1. define the research question and constructs;
2. define inclusion/exclusion and sampling rules;
3. identify which operational fields are genuinely required;
4. perform ethics/privacy/data-policy review;
5. freeze engine/service/protocol versions and provenance requirements;
6. define consent/recruitment if human/user data is involved;
7. define a one-way research export with minimization;
8. hash/version the exported dataset;
9. analyze only after the protocol and primary variables are frozen;
10. report service-context limitations explicitly.

Historical telemetry collected before this procedure is not automatically eligible.

## Contamination controls for the current scientific study

The current first manuscript and IPB evidence must remain insulated from product decisions:
- no customer/service data is merged into the frozen IPB ResearchRun;
- service-specific detector thresholds do not overwrite canonical detector semantics;
- service UX labels do not redefine research states such as absent/unknown/unavailable;
- pricing, funnel or customer-success metrics do not become evidence for P1-P5;
- product feedback may create future hypotheses but cannot retroactively modify the frozen analysis plan;
- any service-driven core semantic change requires the normal research-core version/change-control process.

## Minimum service telemetry schema for future planning

The following is sufficient for architecture/cost planning without creating a research dataset:

| Field | Operational purpose | Research eligible by default? |
| --- | --- | --- |
| job_id (random) | status/support | No |
| engine/protocol version | reproducibility/support | No |
| created_at/completed_at | queue/SLA | No |
| terminal status/failure class | reliability | No |
| request/page/document counts | capacity/cost | No |
| wall-clock duration | capacity/cost | No |
| byte/storage counters | capacity/cost | No |
| quota/rate-limit outcome | abuse/capacity | No |
| account/IP/billing identifiers | auth/security/billing | **Never by default** |
| raw artifacts/submitted URLs | service result | **Never by default** |

A future approved protocol may deliberately export a minimized subset after the promotion procedure above.

## Decision consequences

This plan unblocks specification work in #203 because anti-abuse/retention telemetry can now be designed against an explicit non-research default.

It also defines the data boundary needed by #204: cost/resource counters may be collected operationally for pricing hypotheses without being presented as scientific data.

Neither #203 nor #204 authorizes a public deployment.

## Acceptance check

- Production telemetry is explicitly operational by default: **yes**.
- Operational metrics are separated from candidate research variables: **yes**.
- Human/user data creates an explicit ethics/privacy/consent gate: **yes**.
- Candidate technology-transfer RQs are defined without changing first-paper RQs: **yes**.
- Research-eligible service executions require exact version/provenance binding: **yes**.
- Retrospective convenience use of production telemetry is prohibited: **yes**.
