# PHP-native machine-learning architecture

Status: **experimental**

This document defines the architecture boundary for issue #29. It does not promote machine learning into the default evidence pipeline.

## Purpose

PHP-native ML is evaluated as a complementary analysis path for observable privacy evidence. It can propose candidate classifications, probabilities and review-priority signals, but it does not produce legal-compliance conclusions and does not replace human ground truth.

## Dependency boundaries

The domain owns the semantic contracts. ML frameworks are infrastructure.

```text
Acquired artifact / extracted text
              |
              v
      application experiment
         /           \
        /             \
deterministic       ML contracts
  detectors             |
                         v
                    ML adapter
                         |
                         v
                      Rubix
```

Framework-specific objects must be converted at the adapter boundary. No Rubix dataset, estimator, transformer or serializer type may appear in the evidence, regulatory or review domain API.

## Planned contracts

The exact PHP names are intentionally left to the implementation issues, but the responsibilities are fixed:

### Text classifier

Accepts project-owned training examples and returns project-owned classification results. Training and prediction lifecycle is explicit.

### Classification result

Contains the candidate signal, score/probability where the estimator supports it, abstention state when applicable, and model-artifact identity.

### Feature pipeline

Owns fitted preprocessing state. Training and inference must use the same persisted state.

### Model artifact

Treats the fitted estimator and fitted feature pipeline as one versioned unit. A model artifact is not valid without compatible metadata.

### Model metadata

At minimum records:

- model-artifact format/version;
- ML engine/package and exact version;
- estimator type and relevant hyperparameters;
- feature-pipeline version;
- training dataset identity/version/hash;
- split identity/seed/hash;
- label-mapping version;
- PHP/runtime information;
- training timestamp;
- model/artifact hash;
- evaluation references when available.

### External dataset adapter

Converts a versioned external corpus into project-owned development examples while preserving source identifiers and provenance. It must not write project human-ground-truth records.

## Research semantics

The ML path is intentionally distinct from the five research layers already used by Privacy Evidence:

1. observation;
2. evidence classification;
3. regulatory mapping;
4. human adjudication;
5. research inference.

An ML prediction belongs to automated evidence-classification experimentation or review assistance. It is not a sixth legal layer and must not bypass human adjudication.

## External corpora

External datasets are development/training inputs, not project gold truth.

Before use, an external corpus requires:

- license and attribution review;
- immutable source/version/hash metadata;
- an explicit versioned label mapping;
- leakage-safe grouped splitting;
- language and population-transfer limitations.

Labels that state or imply legal compliance are excluded from the generic evidence taxonomy.

## Deterministic fallback and cold start

The deterministic pipeline remains available regardless of model availability. If there is no valid model artifact, incompatible metadata, insufficient development data or an experimental gate has not been satisfied, deterministic evidence continues normally.

This follows the useful cold-start separation demonstrated by Nextcloud Mail, while keeping the exact thresholds and promotion rules project-specific.

## Model lifecycle

```text
external/project development data
            |
            v
      canonical examples
            |
            v
       deterministic split
            |
            v
    fit feature pipeline
            |
            v
       train estimator
            |
            v
 estimator + fitted pipeline
            |
            v
       model artifact
            |
            v
          inference
```

Training is never implicit during production-style inference.

## Testing strategy

### Contract tests

All classifier implementations must pass shared behavior tests for:

- invalid/untrained state;
- deterministic project-owned input/output shape;
- provenance presence;
- explicit abstention/unsupported states.

### Unit tests

Project-owned logic receives table-driven tests for:

- external label mapping;
- split/grouping;
- artifact metadata validation;
- disagreement/review policy;
- empty/invalid text behavior.

### Integration tests

Small local fixtures cover:

- Rubix train -> save -> load -> predict;
- fitted feature-pipeline round trip;
- CLI commands;
- review-queue integration.

Default tests must not download public datasets or require network access.

### Research evaluation

Model quality is evaluated separately from software correctness. At minimum report per signal:

- confusion matrix;
- precision;
- recall;
- F1;
- support;
- coverage/abstention when relevant;
- traceable false positives and false negatives.

Accuracy alone is insufficient for imbalanced labels.

## Security and resource boundaries

External datasets and model artifacts are data, not trusted executable instructions. Loaders must validate expected format/version/hash before use.

Implementations must bound memory-sensitive dimensions such as input length, vocabulary/features and model artifact size. Default CI uses tiny local fixtures rather than production-size models.

## Promotion rule

No implementation leaves the experimental boundary solely because it performs well on Claudinha or another external corpus.

Promotion requires project-specific independently reviewed evaluation data, documented limitations and an explicit architectural/research decision.
