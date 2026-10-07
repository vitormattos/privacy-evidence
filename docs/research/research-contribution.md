# Scientific contribution and claim boundaries

Contribution model version: **0.1.0**

## Central phenomenon

Privacy Evidence studies **measurement attrition and evidence provenance in empirical web research**.

The central methodological problem is that a declared web population is not necessarily the population that is ultimately measured. Resources can be lost or transformed through normalization, classification, deduplication, DNS/TLS/HTTP failures, browser limitations, protocol exclusions, missing artifacts and analytical uncertainty. If those transitions are not preserved explicitly, a study can silently replace its intended population with a survivor subset and can confuse non-observation with absence of the phenomenon.

Privacy Evidence is the research artifact used to make those transformations observable, versioned and auditable.

## Scientific contribution statement

The project evaluates the following falsifiable contribution:

> Explicitly preserving population transformations, measurement losses, uncertainty states, evidence provenance and human/automated decisions can make large-scale web measurement more auditable and reproducible, while exposing measurement attrition that complete-case or missing-as-negative analyses may conceal.

This is not treated as true by construction. The research program must produce evidence that supports, limits or refutes each proposition below.

## Research context

The research problem originates in a 2025 academic study of public digital resources declared by churches of the Igreja Presbiteriana do Brasil (IPB). A later attempt to reconstruct and extend that study exposed limitations in source preservation, intermediate transformations, failure semantics and reproducibility.

The IPB population is therefore:
- the historical origin of the problem;
- the first real empirical case;
- a context in which the artifact is evaluated.

It is **not** the universal scope of the scientific contribution. Generalization beyond this case requires explicit replication/transferability evidence.

The historical monograph and its published counts remain immutable legacy results. Current protocol semantics must not be retroactively projected onto the 2025 study.

## Artifact contribution vs empirical findings

### Artifact contribution

Privacy Evidence contributes a versioned research instrument that can preserve:
- source population and original declared values;
- deterministic normalization/classification steps;
- acquisition attempts and terminal failure states;
- exact acquired artifacts or browser observations;
- automated evidence classifications and detector versions;
- independent human review and adjudication records;
- regulatory-profile mappings separated from generic evidence;
- run-level code, dataset, protocol, schema and configuration provenance;
- reproducible derived metrics and exports.

The existence of these capabilities is an engineering result. Their scientific value must be evaluated empirically.

### Empirical findings

Empirical findings are conclusions drawn from specific ResearchRuns, reviewed datasets and analyses. They may concern:
- where and why measurement attrition occurs;
- whether attrition changes denominators or substantive estimates;
- whether measurable resources differ systematically from non-measurable resources;
- detector reliability against an independently adjudicated human reference;
- reproducibility of derived outputs;
- transferability to other populations.

Artifact capabilities must not be reported as if they already prove these empirical claims.

## Testable propositions

### P1 — Population accountability

**Proposition:** A provenance-aware measurement pipeline can account explicitly for the transformation of each declared source item into a terminal research state rather than silently dropping unmeasured items.

**Evidence required:**
- a stage-by-stage transformation model;
- reconciled counts at each transition;
- terminal states for all eligible source items;
- traceability from derived units back to source values.

**Evidence that would weaken/refute P1:**
- source items disappear without an explicit state;
- counts cannot be reconciled;
- deduplication or exclusions cannot be reconstructed;
- the final analytical population cannot be traced to the source population.

### P2 — Measurement attrition materially affects analysis

**Proposition:** Explicitly representing unknown/unavailable measurements can materially change denominators, estimates or interpretation compared with complete-case analysis or naive missing-as-negative treatment.

**Evidence required:**
- a frozen sensitivity analysis comparing at least:
  1. complete-case analysis;
  2. an intentionally naive missing/unavailable-as-negative counterfactual;
  3. the canonical provenance-aware analysis;
- effect on selected privacy-evidence estimates and coverage;
- an account of which conclusions remain robust or change.

**Evidence that would weaken/refute P2:**
- alternative missingness treatments produce substantively equivalent conclusions within declared uncertainty;
- attrition is negligible for the evaluated outcomes.

A null or small effect is a valid research result.

### P3 — Provenance enables reproducibility

**Proposition:** Preserving exact inputs, artifacts, protocol/configuration versions and analysis commands allows deterministic derived results to be reproduced independently from a clean environment.

**Evidence required:**
- a frozen replication package;
- clean-environment reproduction;
- expected hashes for deterministic artifacts;
- explicit separation of deterministic reproduction from live-Web replication.

**Evidence that would weaken/refute P3:**
- undocumented local state is required;
- deterministic outputs cannot be regenerated;
- version/provenance metadata is insufficient to explain differences.

### P4 — Automated evidence measurement has quantifiable validity

**Proposition:** Automated detectors can be evaluated as measurement instruments against independent human reference labels with per-signal performance and auditable disagreement/error analysis.

**Evidence required:**
- a frozen evaluation partition not used for final tuning;
- at least two genuine independent human reviewers for the designated reliability subset;
- agreement computed before adjudication;
- preserved pre-adjudication labels;
- adjudicated reference labels;
- confusion matrices, precision, recall, F1, support and abstention/coverage per signal where meaningful.

**Evidence that would weaken/refute P4:**
- agreement is too low to form a stable reference;
- support is insufficient for claimed metrics;
- detector performance is poor or unstable for important signals.

Poor detector performance is a valid finding and must not be hidden by aggregate scores.

### P5 — Core measurement semantics can transfer

**Proposition:** The core protocol can be applied to a meaningfully different web population without silently redefining the constructs being measured.

**Evidence required:**
- a second-population selection rationale;
- application of the same core protocol;
- explicit versioning of any unavoidable adaptations;
- comparison of attrition and evidence observability across contexts.

**Evidence that would weaken/refute P5:**
- substantial construct or protocol redefinition is required;
- the method only works under IPB-specific source assumptions;
- key measurements cannot be operationalized in the second population.

P5 is a downstream proposition and must not be claimed before the transferability study is executed.

## Claims permitted

Subject to the evidence available for a specific study/run, Privacy Evidence may support claims about:
- publicly observable privacy evidence under a specified acquisition/review protocol;
- population transformation and measurement attrition under that protocol;
- acquisition/failure/uncertainty states;
- detector performance against a declared human reference standard;
- reproducibility of deterministic derived outputs from preserved inputs/artifacts;
- differences between versioned ResearchRuns when comparability conditions are satisfied;
- transferability only to the extent directly evaluated.

## Claims not permitted

Website observations alone do **not** establish:
- organization-wide legal compliance or non-compliance;
- complete internal processing inventories;
- validity of an organization's legal bases;
- actual retention behavior;
- organizational security controls not publicly observable;
- correct handling of future data-subject requests;
- absence of a privacy practice merely because the crawler failed to observe it.

The project must not use:
- `unknown` as a synonym for `absent`;
- `unavailable` as a negative label;
- a regulatory profile result as a legal certification;
- detector confidence as legal confidence.

## Novelty boundary

The novelty claim is not "automated privacy scanning", "browser-based privacy measurement" or "reproducible web collection" by itself. Existing research tools already address important parts of those problems.

Any novelty claim must be derived from the focused related-work/tool-landscape review and, when needed, external baseline experiments. Candidate differentiators such as end-to-end population accounting, explicit measurement-loss semantics, human-reference workflow and run-level provenance remain hypotheses until that review is complete.

Do not use "first", "unique", "state of the art" or "better" without documented evidence.

## Relationship to GQM

GQM remains the operationalization mechanism: goals are refined into questions and metrics.

GQM does not, by itself, define the complete research design. The methodological stack and the paper-specific RQs are maintained separately and must remain consistent with this contribution model.

## Change control

A change to:
- the central phenomenon;
- the scientific contribution statement;
- P1–P5 semantics;
- claim boundaries;

is a research-semantic change and requires an explicit version update and methodological review.

Paper-specific RQs may evolve without rewriting historical ResearchRun semantics, provided the variables/metrics they consume remain traceable and versioned.
