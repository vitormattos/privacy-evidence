# Research core and LibreCode service boundary

Status: **concept specification**
Version: **0.1.0**

This document defines a future integration boundary. It does **not** authorize deployment of a public service.

## Goal

Allow LibreCode to build hosted/commercial services on top of Privacy Evidence while preserving the scientific engine as an independently versioned, reproducible and citable research artifact.

## Core principle

The hosted service may orchestrate Privacy Evidence. It must not silently redefine measurement semantics.

A service release must be able to state exactly which research components produced a result:

- Privacy Evidence engine version / Git commit;
- research protocol version;
- schema version;
- detector versions;
- regulatory-profile versions;
- annotation-handbook version where human review is involved;
- service-side configuration that affects acquisition budgets or feature availability.

## Repository boundary

### Research core

Canonical repository:
- `vitormattos/privacy-evidence`

Responsibilities:
- source/import model;
- normalization and resource taxonomy;
- acquisition and browser escalation semantics;
- evidence model and detectors;
- ResearchRun provenance;
- review/adjudication model;
- regulatory profiles;
- research exports/metrics;
- reproducibility tooling;
- research tests, fixtures and protocol docs.

The research core remains:
- open source;
- independently runnable;
- versioned separately from any hosted product;
- citable;
- usable without LibreCode infrastructure.

### Service layer

Recommended future repository:
- `LibreCodeCoop/privacy-evidence-service` or another explicitly approved LibreCode repository.

Responsibilities:
- public/API UI;
- authentication/accounts;
- CAPTCHA/anti-abuse;
- quotas and billing/entitlements;
- job scheduling/orchestration around the engine;
- customer tenancy;
- result retention policy;
- service telemetry;
- notifications;
- commercial report presentation;
- operational monitoring/SLA;
- deployment/infrastructure configuration.

The service must depend on an explicit core release or immutable commit.

## Version contract

Every persisted service scan should record a minimum provenance tuple:

```text
service_version
engine_git_commit
engine_release
protocol_version
schema_version
detector_versions
profile_versions
annotation_handbook_version
measurement_configuration_hash
service_job_id
research_run_id
```

Not every scan is automatically research data. The provenance fields exist so that an approved future study can determine what produced the result.

## Changes that belong in the research core

A change belongs in the core when it alters what is measured or how a measurement state is interpreted.

Examples:
- URL normalization rules;
- resource eligibility;
- crawler escalation semantics;
- detector rules/thresholds;
- evidence taxonomy;
- missing-data states;
- denominator rules;
- profile mapping;
- human-review states;
- ResearchRun provenance semantics.

These changes require normal core testing/versioning and methodological review.

## Changes that belong in the service

A change belongs in the service when it changes product operation without changing measurement semantics.

Examples:
- login flow;
- CAPTCHA provider;
- plan limits;
- customer dashboard;
- invoicing;
- email notifications;
- UI theme;
- tenant administration;
- queue priority by plan;
- report branding.

## Mixed changes

Some operational limits affect what can be observed and therefore cross the boundary.

Examples:
- maximum pages crawled;
- browser escalation disabled on a plan;
- maximum wall time;
- blocked geographic regions;
- disabled detector families;
- data-retention policies that remove evidence needed for audit.

Rule:

> If a service setting can change the scientific meaning, coverage, or reproducibility of a result, it must be recorded as measurement-affecting configuration and exposed in the result provenance.

If the setting changes canonical measurement semantics rather than merely constraining an individual run, the core protocol/version must change.

## Result wording boundary

The research core and service must preserve the same semantic guardrail:

Privacy Evidence reports **publicly observable evidence under a declared protocol**. It does not certify organization-wide legal compliance.

The service may present:
- observed evidence;
- missing/unavailable evidence;
- profile-oriented support/coverage;
- recommendations framed as operational guidance.

The service must not present a generic green/red “LGPD compliant / non-compliant” verdict unless a separately defined legally reviewed product explicitly owns that claim. Such a product would be outside the present research claim model.

## Human review boundary

A future paid expert review may:
- use the same versioned reviewer interface;
- add a service-level workflow;
- create expert-facing reports.

If service reviews are later used in research:
- reviewer type/identity class must remain auditable;
- the research protocol must approve their use;
- operational/customer reviews are not silently relabeled as independent research annotations.

## Data boundary

Service data and research data are separate classifications.

### Service operational data
May include:
- customer/account identifiers;
- submitted domains;
- job state;
- billing/plan information;
- operational logs.

### ResearchRun data
Includes only data captured under an approved research context/protocol.

A production service database must not become a retrospective research dataset by convenience.

Any future research using service users, telemetry or customer scans requires:
- an explicit study question/protocol;
- privacy/data-policy review;
- legal/ethics review where applicable;
- declared sampling and provenance.

## API boundary

Preferred future execution pattern:

```text
service request
  -> validate/authorize/quota
  -> create service job
  -> invoke pinned Privacy Evidence engine
  -> receive ResearchRun/result identifiers
  -> persist service-safe summary + provenance
  -> retain/restrict raw artifacts according to declared policy
```

The service should avoid calling internal classes that bypass the engine's public CLI/API invariants unless an explicit stable programmatic interface is later added to the core.

## Deployment boundary

The research repository must not acquire:
- production customer secrets;
- payment credentials;
- service-only environment configuration;
- customer-specific templates;
- production databases.

Service deployment lives outside the research repository.

## Security boundary

Because user-supplied URLs cause outbound browsing, security controls are service responsibilities but must preserve core network invariants.

The service must not weaken:
- private/loopback/link-local/cloud-metadata blocking;
- TLS verification;
- browser sandboxing;
- bounded resource usage.

Detailed future service controls are specified by #203.

## Licensing and attribution

The research engine remains under its existing free-software license.

A LibreCode service should visibly identify:
- the Privacy Evidence engine/version;
- the open-source project;
- scientific/methodological documentation where appropriate.

Commercial value is expected to come from hosting, scale, monitoring, integrations, expert review, support/SLA and operational convenience rather than hiding the research core.

## Technology-transfer boundary

A hosted service can provide evidence of:
- practical usability;
- operational scalability;
- adoption;
- cost;
- technology transfer.

It does not by itself prove:
- detector validity;
- scientific reproducibility;
- legal correctness.

Those remain governed by the research program.

## Go/no-go

This specification is complete when a future service implementation can be designed without changing core measurement semantics implicitly.

Production implementation remains blocked by:
- #203 security/anti-abuse workflow;
- #204 commercial/cost hypotheses;
- #206 technology-transfer/research-data boundary;
- #207 staged go/no-go approval.


## Technology-transfer research boundary

Future service telemetry remains operational data by default. The protocol for promoting a deliberately minimized subset into a separately approved research study is defined in [technology-transfer-evaluation.md](technology-transfer-evaluation.md). Product use must not retroactively alter or contaminate frozen research runs.
