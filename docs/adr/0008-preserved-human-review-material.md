# ADR 0008: Preserved source material for independent human review

Status: accepted

## Context

Detector excerpts can be a matched keyword without enough context to adjudicate a disclosure. External crawled pages also need their relationship to the original sample to be visible. Missing keyword matches cannot establish missing review material.

## Decision

Annotation packages 1.1.0 embed full inert text extracted from SHA-256-verified local artifacts, deduplicated by artifact hash, together with sampled resource and case-specific document provenance. New samples prepare this material; existing selections can recover it from run exports without live network access or resampling. The interface presents a concrete question and the preserved page before the decision, while instructions and technical provenance are expandable. Automated detector outputs remain hidden and unchanged.

A case is eligible for textual review only with matching provenance and nonempty preserved document text. Missing, corrupt, unsupported and behavioral-only material remains explicitly accounted for in an investigation queue. Import checks eligibility from source records rather than submitted material. Text extraction is not a screenshot, a rendering guarantee or a pre-consent behavioral observation.

## Consequences

Review workflow 1.2.0 changes review conditions without changing collection, detectors or the annotation handbook. Earlier excerpt-only reviews must not be silently pooled with full-context reviews. Completed historical packages remain supported. Enriched packages contain more source data and must retain restricted handling. The generated HTML is self-contained; its JSON export preserves exactly the material shown. Both reviewers must receive the same frozen material before annotation.

References: issue #172; [gold dataset workflow](../research/gold-dataset.md).
