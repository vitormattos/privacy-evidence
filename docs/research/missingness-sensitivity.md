# Missingness sensitivity analysis

Analysis model version: **0.1.0**

## Purpose

This analysis operationalizes paper RQ2 and proposition P2 without modifying canonical evidence states.

It compares three analysis conditions over canonical website measurement units:

1. **Complete-case** — estimate prevalence only among units resolved as `present` or `absent`.
2. **Naive-negative counterfactual** — intentionally treat unresolved applicable units as negative for the purpose of a sensitivity comparison. This is not a recommended or canonical interpretation.
3. **Provenance-aware** — preserve unresolved units explicitly and report observed prevalence, coverage and identification bounds.

## Frozen primary outcomes

Before executing the empirical comparison, the following detector-family outcomes are designated as primary:

- `privacy_notice`;
- `privacy_law_reference`;
- `privacy_contact`;
- `rights_disclosure`;
- `cookie_notice`.

The machine-readable output also reports every current `EvidenceType` so secondary/exploratory signals remain visible without silently redefining the primary analysis after results are inspected.

## Unit of analysis

The denominator starts from canonical website measurement units in `attrition-results.json`.

Duplicate eligible source references are therefore not counted as independent website units.

For a resource/evidence-type pair with multiple artifact-level observations, the aggregation rule is:

- any `present` observation -> `present`;
- otherwise any `unknown`, `unavailable` or `invalid` observation -> unresolved;
- otherwise any `absent` observation -> `absent`;
- only `excluded`/`not_applicable` observations -> excluded from the applicable denominator;
- no observation row -> unresolved.

This ordering is deliberately conservative: an observed absence does not override an unresolved observation from another required artifact, while a positive observation is sufficient to establish public observability for the signal.

## Reported quantities

For each evidence type the output reports:

- canonical units;
- excluded/not-applicable units;
- applicable units;
- present, absent and unresolved counts;
- complete-case numerator, denominator, prevalence and coverage;
- naive-negative numerator, denominator and prevalence;
- provenance-aware observed prevalence, coverage and lower/upper bounds;
- absolute and relative difference between naive-negative and complete-case prevalence.

The provenance-aware bounds are:

- lower bound = present / applicable;
- upper bound = (present + unresolved) / applicable.

These are identification bounds under the deliberately weak assumption that unresolved applicable observations may be either positive or negative. They are not confidence intervals.

## Interpretation

A large difference between complete-case and naive-negative estimates, low coverage, or wide provenance-aware bounds indicates that the substantive estimate is sensitive to missingness semantics.

A small difference is also a valid result and would weaken proposition P2 for that outcome/run.

The analysis does not establish that missingness is random or non-random. Selective measurability is addressed separately by issue #194.

## Artifacts

Running `report <run-id>` produces:

- `missingness-sensitivity.json`;
- `missingness-sensitivity.csv`.

The analysis reads the already exported `attrition-results.json` and `evidence.json` and never rewrites canonical stored observations.

## Completion boundary for #193

The implementation is necessary but not sufficient to close #193.

The issue is empirically complete only after this analysis is executed on the frozen case-study run, the primary-outcome results are inspected under the predeclared procedure, and the repository records which conclusions are robust or unstable.
