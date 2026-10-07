# Measurement attrition and population flow

Analysis model version: **0.1.0**

## Purpose

Measurement attrition is the transformation from the declared source population into the set of canonical web units for which the protocol produces usable observations.

The project treats each transformation as research data. Protocol exclusions, duplicate references and acquisition failures are not silently removed from the accounting.

This analysis supports paper RQ1 and proposition P1 in `research-contribution.md`.

## Stages

### 1. Source population

Every imported source row/resource preserved by the ResearchRun.

No later eligibility decision removes an item from source-population accounting.

### 2. Normalized resource

A source item for which a protocol-derived HTTP/HTTPS representation exists.

A resource without a normalized URL reaches terminal stage `normalization_unavailable`.

### 3. Website-eligible resource

A normalized resource whose versioned `ResourceType` is eligible for website measurement.

Ineligible resources remain in the population and reach terminal stage `protocol_excluded`.

This is a protocol classification, not an acquisition failure.

### 4. Canonical website measurement unit

Multiple eligible source resources may resolve to the same normalized URL. Exactly one eligible resource is selected deterministically as the canonical website measurement unit.

Other eligible aliases reach terminal stage `duplicate_eligible_reference`.

Deduplication reduces the number of measurement units but does not erase the source records.

### 5. Measurement outcome

A canonical website unit receives one of the persisted resource outcome states.

For attrition analysis:

- `measured` -> `fully_measured`;
- `partially_measured` -> `partially_measured`;
- `not_measurable` -> `not_measurable`;
- `missing_outcome` -> `missing_outcome`;
- unexpected future states remain explicit as `other_measurement_status`.

### 6. Analytically observed unit

A canonical website unit is considered analytically observed for the attrition summary when its status is `measured` or `partially_measured`.

This definition does not imply that every evidence signal is observable for that unit. Signal-level unknown/unavailable semantics remain governed by the evidence model.

## Exported artifacts

Every `RunExporter` execution produces:

### `attrition-results.json` / `attrition-results.csv`

One row per source resource with:
- source/resource identifiers;
- normalized URL and resource classification;
- whether normalization succeeded;
- whether it is website-measurement eligible;
- whether it is the canonical eligible website unit;
- measurement status and primary reason;
- terminal attrition stage;
- whether the canonical unit is analytically observed.

### `attrition-summary.json`

Deterministic aggregate counts:
- source population;
- normalized resources;
- website-eligible resources;
- canonical website units;
- fully measured units;
- partially measured units;
- observed units;
- not-measurable units;
- missing-outcome units;
- measurement-loss units;
- canonical-to-observed and canonical-to-fully-measured rates;
- terminal-stage counts;
- failure reasons among canonical non-measurable units.

### `attrition-flow.md`

A deterministically generated Mermaid flow diagram derived from `attrition-summary.json`.

It is a presentation artifact, not an independent data source.

## Reconciliation invariants

For a complete run:

1. Every exported population row produces exactly one attrition row.
2. Every attrition row has exactly one terminal stage.
3. Protocol-excluded and duplicate-reference counts remain distinct from measurement failures.
4. `canonicalWebsiteUnits = fullyMeasuredUnits + partiallyMeasuredUnits + notMeasurableUnits + missingOutcomeUnits + any explicitly reported future canonical status`.
5. `observedUnits = fullyMeasuredUnits + partiallyMeasuredUnits`.
6. `measurementLossUnits = notMeasurableUnits + missingOutcomeUnits`.
7. No resource may disappear because it is malformed, excluded, duplicated or unavailable.

The existing `population-summary.json` complete-accounting invariant remains complementary to these attrition-specific invariants.

## Interpretation rules

### Protocol exclusion is not measurement loss

A social-network resource intentionally excluded by the website protocol is part of the declared population but is not a failed website measurement.

### Duplicate reference is not measurement loss

A duplicate eligible source value maps to another canonical website unit. It is a population transformation.

### HTTP success is not sufficient for measurement success

A fetched HTTP 200 artifact may still be non-measurable, for example when it is an anti-bot challenge.

### Partial measurement is observable but incomplete

A partially measured unit contributes to `observedUnits`, while the reason for its limitation remains explicit. Signal-level analysis must still use each evidence item's state rather than assuming complete observation.

### Unknown is not absent

The attrition layer never converts unavailable/missing acquisition into negative privacy evidence.

## Relationship to RQ2

This artifact defines the accounting needed for #193.

The missingness sensitivity analysis must compare alternative analysis treatments without mutating canonical stored states:
- complete-case;
- deliberately naive missing/unavailable-as-negative counterfactual;
- canonical provenance-aware semantics.

## Relationship to selection bias

Issue #194 may use pre-outcome characteristics from these exports to test whether measurability is associated with resource characteristics.

Association does not establish the cause of failure and must not be interpreted causally without an appropriate design.

## Versioning

Changes to terminal-stage meaning or the definition of analytically observed units are research-semantic changes and require a version bump of this analysis model and, where they alter protocol meaning, the corresponding protocol version review.
