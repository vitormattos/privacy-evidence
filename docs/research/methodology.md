# Methodological stack

Methodology model version: **0.1.0**

Privacy Evidence uses complementary methodological layers. No single method is treated as sufficient for the whole research program.

## 1. Engineering Research / Design Science

Privacy Evidence is a technological artifact invented and evaluated to address a research problem. This layer explains why the artifact is needed, what problem it addresses, how it is designed, and how its strengths, weaknesses and limitations are evaluated in context.

Primary references:
- ACM SIGSOFT Empirical Standards — Engineering Research: https://www2.sigsoft.org/EmpiricalStandards/docs/standards
- Wieringa, *Design Science Methodology for Information Systems and Software Engineering*: https://doi.org/10.1007/978-3-662-43839-8
- Engström et al., design-science alignment in software engineering: https://doi.org/10.1007/s10664-020-09818-7

The artifact is not considered scientifically validated merely because it exists or passes software tests. Empirical evaluation is required through the IPB case, human-reference validation, reproducibility checks, external baseline comparison and transferability/replication where completed.

## 2. Goal–Question–Metric (GQM)

GQM operationalizes goals into questions and metrics. It answers what the project wants to understand, which research questions operationalize that goal, and which data/metrics are needed.

Canonical mappings:
- `research-contribution.md`
- `research-model.md`
- `research-model.yaml`

GQM does not replace sampling, validity analysis, artifact evaluation, human annotation methodology or replication design.

## 3. Observational empirical context

The first real-world context is the IPB web-resource population derived from the 2025 monograph lineage.

This context is used to evaluate how the artifact behaves on a non-synthetic population, where measurement attrition occurs, and how observable privacy evidence is produced under the protocol.

The IPB case is selected because it is the historical source of the research problem. It is not presumed representative of all organizations.

## 4. Sampling and population accounting

The project follows explicit population/sampling rules:
- preserve the source frame;
- record filtering, normalization, classification and deduplication transitions;
- justify any subset used for human review or detector evaluation;
- report sample/population size and strata;
- never infer representativeness from random selection alone;
- keep failed/unavailable observations visible.

Reference:
- ACM SIGSOFT Sampling Supplement: https://www2.sigsoft.org/EmpiricalStandards/docs/supplements

This layer is central to paper RQ1/RQ2 because the study explicitly investigates the difference between intended and measurable populations.

## 5. Human-reference validation

Automated detectors are treated as measurement instruments.

Validation uses:
- independently reviewed labels;
- equivalent reviewer-neutral packets;
- a versioned annotation handbook;
- detector state/confidence hidden during independent annotation;
- agreement computed before adjudication;
- immutable pre-adjudication decisions;
- a separate adjudicated reference;
- per-signal performance metrics.

The manuscript should prefer the terms **human reference** or **adjudicated reference labels** rather than implying that human judgement is infallible ground truth.

## 6. Quantitative detector evaluation

Where support permits, report:
- TP, FP, TN, FN;
- precision;
- recall;
- F1;
- support;
- coverage/abstention;
- specificity/NPV when useful.

Accuracy alone is insufficient for imbalanced signals.

Agreement and detector performance answer different questions:
- agreement evaluates reliability of human annotation;
- detector metrics evaluate automated classification against the adjudicated reference.

## 7. Reproduction and replication

**Reproduction:** regenerate deterministic outputs from the same declared inputs, code, protocol, configuration and preserved artifacts.

**Replication:** repeat measurement on newly acquired live-Web observations under the same declared method.

**Independent reproduction:** a non-implementer follows the frozen package/instructions and attempts to regenerate deterministic outputs without hidden local state.

The live Web is temporally unstable; byte-identical website responses are not required for replication.

Canonical definitions: `reproducibility.md`.

## 8. External baseline comparison

Existing privacy-measurement tools are prior art and may act as baselines.

A comparison must:
- state a narrow question;
- freeze tool/version/config/sample before interpreting results;
- compare commensurable dimensions;
- avoid generic “which tool is better” framing;
- preserve failures/non-equivalence.

WEC is currently the preferred acquisition/evidence baseline candidate.

## 9. Open science and replication package

A publication-grade package should contain, where redistribution rules permit:
- source/input manifest;
- protocol/RQ/model versions;
- code release/tag;
- ResearchRun manifest;
- non-restricted derived data;
- analysis commands;
- permitted human-reference data;
- tables/figures;
- environment/runtime metadata;
- hashes;
- instructions;
- citation metadata and persistent identifiers.

Restricted third-party raw material may be represented by hashes/metadata rather than redistributed.

## 10. Validity framework

Threats are analyzed throughout the study rather than appended only at publication time.

Current categories include construct validity, sampling/external validity, temporal validity, measurement reliability, browser effects, network failures, reviewer/researcher bias and conclusion validity.

Each RQ in `research-model.md` names its dominant validity threats.

## 11. Method-to-question mapping

| Method/layer | Primary purpose | Main RQs |
|---|---|---|
| Engineering Research / Design Science | build/evaluate artifact | all; especially RQ3/RQ4 |
| GQM | operationalize goals/questions/metrics | all |
| observational IPB case | real-context evaluation | RQ1/RQ2 |
| sampling/population accounting | define intended/measured populations | RQ1/RQ2 |
| human-reference annotation | create auditable reference labels | RQ4 |
| agreement statistics | validate annotation reliability | RQ4 |
| detector metrics | measure automated classification validity | RQ4 |
| clean-room reproduction | evaluate provenance/reproducibility | RQ3 |
| external tool comparison | novelty/relative capability evidence | supporting claims |
| second-population study | transferability/external validity | conditional RQ5 |

## 12. Methodological anti-patterns

The project must avoid:
- selecting a method because it produces a preferred result;
- treating implementation effort as empirical evidence;
- collapsing failed measurements into negative labels;
- changing final-evaluation detector rules after inspecting final human labels without versioning a new evaluation;
- reporting several immature/disjointed studies as if breadth compensated for weak evidence;
- undisclosed HARKing of confirmatory claims;
- claiming external generality from one contextual population;
- listing related work only to dismiss it rather than synthesize overlap.

## Change control

Changes to constructs, final-evaluation sampling, reviewer decision semantics, detector semantics or denominator semantics require the corresponding protocol/schema/handbook/version review.
