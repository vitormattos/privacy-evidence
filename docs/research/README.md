# Research documentation

Privacy Evidence treats software behavior as part of the research instrument. This directory is the canonical index for the scientific model, measurement semantics, empirical validation and reproducibility rules.

## Start here

1. **Scientific contribution** — [research-contribution.md](research-contribution.md)  
   Central phenomenon, falsifiable contribution, propositions P1–P5 and claim boundaries.

2. **Research model** — [research-model.md](research-model.md) and [research-model.yaml](research-model.yaml)  
   Research-program questions, first-manuscript RQs and the trace from RQ to constructs, variables, commands, artifacts and analyses.

3. **Methodology** — [methodology.md](methodology.md)  
   Engineering Research / Design Science, GQM, observational evaluation, human-reference validation, reproduction/replication and their roles.

4. **Protocol** — [protocol.md](protocol.md)  
   Research object, units, layers, missing-data semantics and versioned protocol rules.

5. **Empirical-standards checklist** — [standards-compliance.md](standards-compliance.md)  
   Evidence-backed ACM SIGSOFT Empirical Standards readiness and remaining gaps.

## Measurement and analysis

- [variables.md](variables.md) — variable definitions and measurement semantics.
- [data-dictionary.md](data-dictionary.md) — persisted/exported field semantics.
- [metrics.md](metrics.md) — denominator and derived-metric rules.
- [measurement-attrition.md](measurement-attrition.md) — stage-by-stage population accounting.
- [results/ipb-2026-10-05-attrition.md](results/ipb-2026-10-05-attrition.md) — frozen reconstructed RQ1 attrition result for the 2026-10-05 full IPB ResearchRun.
- [missingness-sensitivity.md](missingness-sensitivity.md) — predeclared complete-case, naive-negative and provenance-aware sensitivity analysis for RQ2.
- [results/ipb-2026-10-05-missingness-sensitivity.md](results/ipb-2026-10-05-missingness-sensitivity.md) — frozen RQ2 result for the 2026-10-05 full IPB ResearchRun.
- [selective-measurability.md](selective-measurability.md) — predeclared association analysis for whether measurable units are a selective subset.
- [results/ipb-2026-10-05-selective-measurability.md](results/ipb-2026-10-05-selective-measurability.md) — frozen #194 result for the 2026-10-05 full IPB ResearchRun.
- [evaluation.md](evaluation.md) — detector evaluation and final reference design.
- [validity-threats.md](validity-threats.md) — construct, external, temporal, measurement and conclusion threats.
- [longitudinal-comparison.md](longitudinal-comparison.md) — versioned longitudinal-comparison rules.

## Human review

- [annotation-handbook.md](annotation-handbook.md) — reviewer decision semantics.
- [gold-dataset.md](gold-dataset.md) — independent review, agreement, adjudication and evaluation workflow.

Human annotation is an explicit gate. Automated agents may prepare packages and compute results, but they must not fabricate independent human labels.

## Reproducibility and data

- [reproducibility.md](reproducibility.md) — reproduction vs live-Web replication and package requirements.
- [versioning.md](versioning.md) — research-semantic versioning.
- [data-policy.md](data-policy.md) — publication/restriction policy.
- [citation.md](citation.md) — citation guidance.

## Related work and novelty

- [literature-review.md](literature-review.md) — focused scientific background.
- [tool-landscape-review.md](tool-landscape-review.md) — privacy/web-measurement tool landscape.
- [novelty-audit.md](novelty-audit.md) — safe, unsafe and unresolved novelty claims.

## Historical origin

- [legacy-2025-ipb.md](legacy-2025-ipb.md) — immutable 2025 IPB/TCC baseline and compatibility mapping.

The historical study motivates the problem. Current protocol semantics must not be projected backward onto those published results.

## Experimental/future work

The repository also contains ML-related research notes and browser-backend evaluations. Their presence does not mean they are part of the current central manuscript contribution. Open issues #112 and #113 are explicitly post-PoC research concepts.

## Execution state

GitHub issue **#88 — Master execution roadmap and phase gates** is the durable operational source for what is executable, blocked by humans, or intentionally deferred.

A capability existing in code does not mean the corresponding RQ is answered. Use the evidence-status rules in [research-model.md](research-model.md).
