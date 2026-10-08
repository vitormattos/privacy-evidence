# LibreCode service offers, cost model and pricing hypotheses

Specification version: **0.1.0**

Status: **commercial hypothesis / concept only**. This document does not authorize production deployment, billing integration or legal-compliance certification.

This specification implements issue #204. It uses the research/service boundary in `service-boundary.md`, the technology-transfer boundary in `technology-transfer-evaluation.md`, and the safety limits in `public-scan-security.md`.

## Initial resource measurement used for cost hypotheses

The first cost baseline is not a cloud invoice. It is instrument telemetry from a completed Privacy Evidence run over the frozen 12-target WEC-comparison sample.

Provenance:
- GitHub Actions run: `37715312249`;
- Privacy Evidence ResearchRun: `01a1193a-65a7-77d5-af39-8d2a33d15cdb`;
- engine Git commit: `ce944d76adf06b93e76d2d69e6a313b1c2c06253`;
- dataset SHA-256: `9dc8287006859e93cd3091b14d63ab200e297ba5109199bbacc9e447e7e55c80`;
- protocol: `0.1.0-draft`;
- artifact: `11523164403`.

The Privacy Evidence portion completed successfully even though the surrounding WEC experiment later failed in WEC's PDF-reporting path.

Observed Privacy Evidence telemetry:

| Measure | 12-target run | Mean per imported target |
| --- | ---: | ---: |
| imported resources | 12 | 1.00 |
| acquired documents | 158 | 13.17 |
| acquired bytes | 27,896,037 B | 2,324,670 B (~2.32 MB) |
| browser pages acquired | 15 | 1.25 |
| evidence items | 3,542 | 295.17 |
| fetch-stage jobs | 163 | 13.58 |
| browser-stage jobs | 22 | 1.83 |
| fetch stage elapsed aggregate | 495.9 s | 41.33 s |
| browser stage elapsed aggregate | 45.5 s | 3.79 s |
| peak instrument memory counter | 16 MiB | not safely attributable per target |

Important limitations:
- stage elapsed counters are aggregate instrument timings, not end-to-end billable CPU seconds;
- the memory counter does not necessarily include all browser child-process memory;
- acquired bytes are not equivalent to retained storage after compression/TTL;
- this is a heterogeneous 12-target sample, not a production-load benchmark;
- no human-review labor is included;
- network/compute prices depend on the future hosting architecture.

Therefore this baseline is sufficient to identify cost drivers and testable price bands, but not to claim a final margin.

## Cost model

Define the marginal cost of one scan as:

```text
C_scan =
    C_http_compute
  + C_browser_compute
  + C_network
  + C_storage
  + C_queue_platform
  + C_observability
  + C_abuse/security
  + C_human_review_if_any
```

For a recurring account:

```text
C_month =
    Σ C_scan
  + C_retained_results
  + C_account/tenant
  + C_support
  + C_notifications/integrations
```

Human review is intentionally separated because it can dominate the automated infrastructure cost.

### Required measurable inputs

Before final pricing, collect:
- wall-clock worker occupation by HTTP/browser stage;
- CPU-seconds including browser child processes;
- peak RSS/container memory;
- outbound/inbound bytes;
- retained artifact bytes at each TTL;
- database/queue operations;
- successful/partial/failed scan mix;
- browser-escalation rate;
- retries;
- support minutes per account;
- expert-review minutes per reviewed site/report.

## Personas and problems

### P1 — Individual technical/privacy practitioner

Problem: needs a quick, reproducible view of observable privacy signals for one public site.

Wants:
- low-friction scan;
- understandable evidence/failure states;
- shareable result;
- no legal-certification claim.

### P2 — Consultant / DPO / privacy team

Problem: needs repeatable reports, history and expert interpretation across a manageable portfolio.

Wants:
- saved targets;
- recurring scans;
- change history;
- richer exports;
- optional expert review.

### P3 — Organization / cooperative / public-sector IT team

Problem: needs portfolio-level visibility over many sites/services and operational follow-up.

Wants:
- bulk/API;
- tenant/team access;
- scheduled monitoring;
- auditable provenance;
- integration/export.

### P4 — Researcher

Problem: needs the open research instrument and reproducible outputs without commercial lock-in.

Wants:
- self-hosted/open engine;
- protocol/version control;
- machine-readable outputs;
- replication support.

The research edition remains open and is not artificially crippled to force SaaS adoption.

## Offer hypotheses

Prices below are **experiment bands**, not approved prices. They exist to test willingness-to-pay and unit economics.

### Offer A — Free public scan

Target: P1 and lead generation.

Scope:
- anonymous one-site scan;
- current observable evidence summary;
- explicit unavailable/unknown states;
- short TTL;
- no stored history;
- safety quotas from #203.

Price hypothesis: **R$ 0**.

Value hypothesis:
> A useful free result creates enough trust/intent that a measurable fraction of users request saved monitoring, detailed reporting or expert help.

Primary cost drivers:
- automated compute/browser/network;
- abuse prevention;
- temporary artifact storage.

Risk:
- abuse/load creates cost without qualified demand.

Validation:
- completion rate;
- cost per accepted/completed scan;
- repeat/domain-owner behavior;
- CTA conversion to account/contact;
- abuse/challenge rate.

### Offer B — Detailed self-service report

Target: P1/P2.

Scope:
- one target;
- versioned detailed report/export;
- preserved service-safe result for a defined TTL;
- richer evidence/provenance explanation;
- no human legal opinion.

Initial price test bands:
- **R$ 49**
- **R$ 99**
- **R$ 199**

Do not interpret the highest accepted price as proof of value; test conversion and support burden.

Value hypothesis:
> Users will pay for durable, auditable evidence/provenance and a detailed report when the free scan demonstrates relevance.

Cost drivers:
- automated scan;
- report/storage;
- payment/support overhead.

Risk:
- users expect a legal compliance certificate despite explicit wording.

Validation:
- checkout conversion by price band;
- refund/support-request rate;
- report download/use;
- gross contribution after measured infrastructure/payment cost.

### Offer C — Recurring monitoring

Target: P2/P3.

Scope:
- saved targets;
- scheduled rescans;
- versioned change history;
- notifications;
- bounded retention.

Initial monthly hypothesis per monitored domain:
- **R$ 39/domain/month** for low-frequency monitoring;
- **R$ 79/domain/month** for more frequent monitoring/history;
- portfolio bundles tested separately.

Value hypothesis:
> Change detection and persistent history create recurring value beyond a one-off report.

Cost drivers:
- scan frequency;
- retained summaries/artifacts;
- notification volume;
- support.

Risk:
- web variability produces noisy changes and support burden.

Validation:
- activation-to-second-scan rate;
- retained domains after 30/90 days;
- change events actually opened/acted upon;
- scan cost/domain/month;
- churn.

### Offer D — Bulk/API

Target: P2/P3.

Scope:
- authenticated asynchronous submission;
- quotas;
- status/result API;
- machine-readable summaries;
- no arbitrary webhook until SSRF-safe callback design exists.

Initial pricing hypothesis:
- base platform fee **R$ 299–999/month** depending on quota;
- usage component **R$ 1–5 per completed target scan** after included volume.

These bands are intentionally broad until per-scan infrastructure cost and demand are measured.

Value hypothesis:
> Teams integrating repeatable evidence collection will pay for orchestration, quotas, provenance and support even though the engine itself remains open source.

Cost drivers:
- scan volume;
- concurrency;
- support/SLA;
- storage/export;
- tenant isolation.

Risk:
- high-volume users exceed safe concurrency or can self-host cheaply enough that hosted value is insufficient.

Validation:
- API pilot completion;
- accepted volume;
- measured marginal cost/scan;
- willingness-to-pay interview/pilot;
- self-host-vs-hosted decision reasons.

### Offer E — Expert review / consulting

Target: P2/P3.

Scope:
- human interpretation of collected evidence;
- implementation/remediation guidance;
- optionally versioned expert-reviewed report;
- explicitly not an automatic legal certification.

Initial price hypothesis:
- **R$ 500–1,500 per target/report** for bounded expert review;
- custom portfolio/project pricing when scope exceeds the standard review protocol.

Final price must be derived primarily from measured reviewer time and required expertise, not automated scan cost.

Value hypothesis:
> Human interpretation and prioritized remediation are valuable where automated evidence alone is ambiguous.

Cost drivers:
- expert minutes;
- QA/second review where promised;
- report preparation;
- meetings/support.

Risk:
- unbounded consulting scope destroys margin or creates unauthorized legal-opinion expectations.

Validation:
- review minutes/report;
- revision rounds;
- acceptance/usefulness feedback;
- conversion from automated result;
- contribution margin using an explicit labor rate.

### Offer F — Open research edition

Target: P4.

Scope:
- existing open-source engine;
- self-hosting;
- research protocol/docs;
- reproducible exports.

Price: **R$ 0 license fee** for the open-source software.

Optional paid value:
- installation/support;
- managed research environment;
- training;
- custom integration;
- replication assistance.

Value hypothesis:
> Keeping the research core genuinely open strengthens trust, adoption and external validation while commercial value comes from operation and expertise.

## Funnel hypothesis

```text
public discovery
  -> free scan
  -> useful/understandable result
  -> account or contact intent
  -> detailed report / monitoring / expert review
  -> portfolio/API adoption where appropriate
```

Do not optimize only for raw scan volume. The useful funnel denominators are:
- accepted scans;
- completed/partial scans;
- qualified domain-owner/practitioner sessions;
- CTA intent;
- paid conversion;
- recurring retention.

## Unit-economics gates

No paid offer should leave hypothesis stage until:

1. a valid production-like benchmark records complete CPU/memory/network/storage counters;
2. `C_scan` can be estimated by scan outcome class;
3. browser-heavy and failure-heavy tails are measured;
4. artifact TTL/storage behavior is included;
5. payment/support overhead is included for paid plans;
6. human-review labor is timed separately;
7. the proposed price has a declared target contribution margin.

Suggested decision metric:

```text
contribution_margin =
  (revenue - variable_infrastructure - payment_fees - variable_support - human_review_labor)
  / revenue
```

No target margin is declared here; that is a LibreCode commercial governance decision.

## What must never be sold implicitly

The service must not imply that payment buys:
- legal certification;
- complete organizational privacy assessment;
- proof of LGPD/GDPR compliance/non-compliance;
- certainty where evidence is unavailable;
- a guarantee that all trackers/data flows were observed.

Paid tiers can buy convenience, retention, scale, review and support—not stronger truth semantics.

## Go/no-go experiments

| Offer | Minimum experiment | Primary success evidence | Primary failure evidence |
| --- | --- | --- | --- |
| free scan | internal/private limited pilot | bounded cost + usable result + manageable abuse | cost/abuse dominates or outputs confuse users |
| detailed report | price-page/concierge test before automation | paid intent at sustainable support load | no willingness-to-pay / legal-certification expectation |
| monitoring | 30–90 day private pilot | repeat use and useful change events | noisy changes / no retention |
| bulk/API | design-partner pilot | real integration volume + hosted convenience value | self-host preferred, unsafe load or support burden |
| expert review | concierge pilot | bounded review time + actionable usefulness | unbounded scope / low conversion |
| research services | researcher/support pilot | reproducibility/support demand | no external demand |

## Metrics required for #207 go/no-go

Commercial:
- qualified scan-to-contact conversion;
- paid conversion;
- recurring retention/churn;
- average revenue per account/domain;
- support minutes/account.

Operational:
- cost per completed/partial/failed scan;
- browser escalation rate;
- p50/p95 job wall time;
- p50/p95 CPU/memory;
- network bytes/scan;
- retained bytes/domain/day;
- queue saturation/error rate;
- abuse/challenge rate.

Quality/claim safety:
- proportion of results with unresolved evidence;
- user misunderstanding/support incidents about compliance wording;
- expert-review disagreement/escalation rate;
- provenance completeness.

## Acceptance check

Every candidate offer now has:
- target user: **yes**;
- measurable value hypothesis: **yes**;
- explicit cost drivers: **yes**;
- initial price/usage hypothesis: **yes**;
- primary risk: **yes**;
- validation experiment: **yes**.

The cost model is intentionally provisional because the first telemetry baseline is an engineering measurement, not a production hosting invoice.
