# Selective measurability analysis

Analysis model version: **0.1.0**

## Purpose

This analysis operationalizes issue #194 and supports interpretation of paper RQ1.

The question is whether canonical web units that become analytically measurable differ systematically from canonical units that do not. A systematic association would indicate that complete-case analyses may describe a selective survivor population rather than a random subset of the declared web population.

The analysis is descriptive/inferential and does not identify causal mechanisms.

## Outcome

The binary outcome is defined before inspecting associations:

- **measurable**: canonical website measurement unit with `measurementStatus` equal to `measured` or `partially_measured`;
- **non-measurable**: any other canonical measurement outcome, including `not_measurable`, `missing_outcome`, and future explicit canonical states.

Non-canonical duplicate source references are not independent units and are excluded from this analysis.

## Predeclared predictors

Only exported variables available before the final privacy-evidence result are used:

1. **normalized URL scheme** — e.g. `https` versus `http`;
2. **protocol resource type** — `institutional_website` versus `third_party_hosted_page` where both occur;
3. **duplicate-group membership** — whether the canonical unit represents more than one declared source reference.

The current export does not provide a valid pre-outcome hosting/provider variable or a complete redirect-pattern variable for failed units. Those predictors are therefore not synthesized or inferred from incomplete downstream data.

## Statistical procedure

For each categorical level, the analysis reports:

- support;
- measurable/non-measurable counts;
- measurability rate;
- comparator (all other levels) counts and rate;
- absolute rate difference;
- odds ratio;
- two-sided Fisher exact p-value;
- Holm-adjusted p-value across all reported level tests.

Fisher's exact test is used because sparse categories and zero cells are plausible. Odds ratios with a zero cell use the Haldane-Anscombe 0.5 correction solely to keep the effect-size estimate finite.

The one-versus-rest tests are not independent. Holm correction is therefore used to control the family-wise error rate across the reported exploratory associations.

## Interpretation

A predictor is evidence of selective measurability only when the observed effect is substantively non-trivial and compatible with the inferential result. Statistical significance alone is not sufficient.

Any association must be described as an association. It does not show that a scheme, resource type, or duplicate grouping caused the measurement outcome.

Class imbalance and small category support must be reported with the result. A null association is a valid finding.

## Artifacts

Running `report <run-id>` produces:

- `selective-measurability.json`;
- `selective-measurability.csv`.

The analysis consumes the deterministic `attrition-results.json` and `population-results.json` exports.

## Completion boundary for #194

Implementation does not by itself answer #194. The issue is complete only when this frozen analysis is executed on the designated case-study ResearchRun and the repository records whether there is evidence of selective measurability and what threat, if any, it creates for complete-case interpretation.
