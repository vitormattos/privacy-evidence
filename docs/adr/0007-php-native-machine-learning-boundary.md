# ADR 0007 — Isolate PHP-native machine learning behind project-owned contracts

Status: Accepted
Date: 2026-10-03

## Context

Privacy Evidence already separates acquisition, observable evidence classification, regulatory mapping, human review and research inference. The repository also contains an experimental dependency-free multinomial Naive Bayes baseline under `PrivacyEvidence\\Experiment\\Text`.

Issue #29 expands this experimental work to PHP-native machine learning with Rubix ML and external development datasets. The new work must not make the domain model depend on a specific ML framework, must not promote external-corpus labels into regulation-agnostic evidence semantics, and must remain auditable and reproducible.

Relevant maintained precedents in the Nextcloud organization use Rubix ML in three different ways:

- Nextcloud Mail separates feature extraction from the estimator, persists fitted models/transformers and falls back to rules during cold start.
- Nextcloud Suspicious Login separates historical training data from later validation data and persists model metadata and evaluation metrics.
- Nextcloud Recognize isolates clustering from the upstream process that generates vectors.

These are useful architectural precedents, not APIs to copy verbatim.

## Options considered

### Couple evidence detectors directly to Rubix

This is simple initially but leaks framework types and lifecycle concerns into the evidence domain. It also makes it harder to keep the existing dependency-free baseline and to compare multiple implementations consistently.

### Add a generic project-owned ML boundary and adapt Rubix behind it

This introduces a small abstraction layer but keeps the domain independent of Rubix and allows the dependency-free baseline, Rubix estimators and future implementations to share the same application-level contracts.

### Treat ML as a separate external service

This would isolate dependencies, but it adds deployment and network requirements that are unnecessary for the current PHP-native experiment and weakens the goal of local, reproducible inference.

## Decision

Use project-owned ML contracts at the experimental/application boundary and keep framework-specific code in adapters.

The dependency direction is:

```text
Domain and research semantics
            ^
Project-owned ML contracts
            ^
Application/experiment services
            ^
Infrastructure adapters
            ^
Rubix ML
```

Initial contracts will cover:

- classifier training and prediction;
- structured classification results;
- fitted model artifacts;
- model and dataset provenance;
- feature-pipeline identity/version.

Rubix classes must not appear in `PrivacyEvidence`, `EvidenceType`, regulatory-profile contracts or human-review domain contracts.

ML output is an automated candidate/suggestion. It must not silently replace deterministic evidence and must never be persisted as human ground truth.

External dataset labels must pass through an explicit versioned mapping before they can be interpreted as project candidate signals. Regulatory or compliance labels from external corpora are not project evidence types.

Deterministic acquisition, crawling and artifact preservation remain operational when ML is unavailable.

## Promotion gate

Implementations remain experimental until they are evaluated against the project's independently reviewed gold dataset.

Performance on an external corpus or synthetic fixtures can justify continued experimentation, but cannot by itself promote an ML implementation into the default evidence path.

A promotion decision must record:

- exact model artifact and estimator version;
- training/development dataset identity, version and hash;
- feature-pipeline version;
- evaluation dataset version/hash;
- per-signal precision, recall, F1, support and coverage/abstention where applicable;
- language/domain limitations;
- traceable error analysis.

## Consequences

Positive:

- the evidence and regulatory domains remain framework independent;
- the existing Naive Bayes baseline can be compared through the same boundary;
- fitted transformers and model metadata can be versioned as research artifacts;
- Rubix can be upgraded or replaced without changing domain semantics.

Negative:

- a small adapter/contract layer must be maintained;
- model lifecycle and provenance require explicit artifact design;
- experiments may duplicate some convenience abstractions already present in Rubix.

Follow-up:

- #100 adds the isolated Rubix backend;
- #101 adapts the dependency-free Naive Bayes baseline;
- #102–#104 define external dataset provenance, label mapping and leakage-safe splitting;
- #105–#110 implement and validate the first Rubix path.
