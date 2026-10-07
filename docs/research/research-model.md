# Research model and RQ-to-evidence traceability

Research model version: **0.1.0**

This document separates the long-lived Privacy Evidence research program from the focused questions of the first scientific manuscript.

The central phenomenon and propositions are defined in `research-contribution.md`.

## Research-program questions

These questions define the broader program and may be answered across multiple studies.

### PRQ1 — Population observability
What proportion of declared digital resources becomes technically observable under a versioned acquisition protocol, and where is measurement lost?

### PRQ2 — Resource characterization
What types of public digital resources are declared, discovered or excluded, and how do those types relate to observability?

### PRQ3 — Observable privacy evidence
Which defined privacy-evidence signals are publicly observable under the protocol?

### PRQ4 — Evidence intensity and uncertainty
How complete, specific and review-dependent are the observed signals?

### PRQ5 — Regulatory-profile interpretation
How do reviewed generic evidence items map to versioned LGPD, GDPR and cookie/ePrivacy profiles under explicit applicability rules?

### PRQ6 — Measurement-instrument validity
How accurately and reliably do automated detectors reproduce independently adjudicated human reference labels?

### PRQ7 — Acquisition/resource cost
What HTTP/browser/resource cost is required to produce observations under bounded crawl budgets?

### PRQ8 — Reproducibility
To what extent can deterministic analytical outputs be independently regenerated from preserved source data, artifacts, code, protocol and configuration?

### PRQ9 — Transferability
To what extent can the core measurement semantics be applied to a meaningfully different web population without redefining the constructs being measured?

### PRQ10 — Longitudinal change
When comparable ResearchRuns exist, which observed changes represent content/evidence change rather than acquisition or protocol noise?

PRQ10 is intentionally post-PoC/future work and is governed by issue #113.

## First scientific manuscript

Working focus: **measurement attrition and evidence provenance in empirical web research**, evaluated through the IPB case and detector-validation workflow.

### RQ1 — Measurement attrition

**Question**

How does a declared web population transform into an effectively measurable population under a reproducible acquisition protocol, and what factors account for measurement loss?

**Primary proposition:** P1.

### RQ2 — Analytical impact of missingness

**Question**

How does explicitly representing non-observability and measurement failures affect denominators, estimates and interpretation compared with complete-case analysis and a naive missing-as-negative treatment?

**Primary proposition:** P2.

### RQ3 — Reproducibility through provenance

**Question**

To what extent can the analytical population and deterministic derived findings be independently reproduced from preserved inputs, artifacts, protocol versions and configurations?

**Primary proposition:** P3.

### RQ4 — Measurement validity

**Question**

How reliably do automated privacy-evidence detectors reproduce independently adjudicated human classifications on a frozen evaluation dataset?

**Primary proposition:** P4.

### Conditional RQ5 — Transferability

**Question**

To what extent can the same core measurement protocol be applied to a meaningfully different web population without changing its measurement semantics?

**Primary proposition:** P5.

RQ5 is included in the first manuscript only if issue #201 completes with sufficient evidence before manuscript freeze. Otherwise it becomes a follow-up study and the first manuscript reports external-validity limits explicitly.

---

# RQ-to-evidence traceability

## RQ1 — Measurement attrition

| Element | Trace |
|---|---|
| Phenomenon | measurement attrition |
| Constructs | intended population; canonical web unit; technical observability; analytical eligibility |
| Primary variables | source value/id; normalized URL; resource type; eligibility state; canonical/deduplicated unit; acquisition state; evidence observability |
| Existing canonical definitions | `variables.md`, `data-dictionary.md`, `metrics.md` |
| Existing data producers | source import/normalization; ResearchRun persistence; acquisition workers; report/export pipeline |
| Existing commands | `source:import`, `source:stats`, `source:select`, `run`, `workers`, `status`, `report` |
| Existing artifacts | run manifest; resources/documents/evidence exports; `population-summary.json`; `population-results.json`; status/failure outputs where present |
| Required analysis | reconcile every transition from source row to terminal analytical state; quantify losses by stage/reason |
| Implementation gap | dedicated stage-transition/attrition export and regenerated flow visualization |
| Governing issue | #192 |
| Planned outputs | transition table/JSON; attrition taxonomy; flow figure; reconciled denominators |
| Manuscript section | Results — RQ1 |
| Main validity threats | source coverage; deduplication semantics; temporal/network failures; construct boundary |

### RQ1 acceptance evidence

RQ1 is answerable only when:
- every source item can be reconciled through declared stages;
- transition totals balance;
- exclusion/unavailable/invalid states remain visible;
- the analysis distinguishes source population from canonical web units.

## RQ2 — Analytical impact of missingness

| Element | Trace |
|---|---|
| Phenomenon | analytical effect of measurement loss |
| Constructs | observable evidence prevalence; missingness semantics; denominator stability |
| Primary variables | observation state; acquisition/observability state; evidence type; eligible denominator |
| Existing canonical definitions | `metrics.md`, `variables.md`, `validity-threats.md` |
| Existing data producers | evidence store; reviewed evidence; report/export pipeline |
| Canonical semantics | present/absent/unknown/unavailable/invalid/excluded/not-applicable are distinct |
| Required conditions | A: complete-case; B: intentionally naive missing/unavailable-as-negative counterfactual; C: canonical provenance-aware |
| Implementation status | deterministic sensitivity-analysis export is implemented by `report`; empirical execution/freeze on the designated case-study run remains pending |
| Governing issue | #193 |
| Planned outputs | per-signal estimates under A/B/C; denominator/coverage differences; robust/unstable conclusion table |
| Manuscript section | Results — RQ2 |
| Main validity threats | arbitrary outcome selection; rare signals; treating counterfactual B as endorsed method |

### RQ2 acceptance evidence

RQ2 is answerable only after the outcomes and comparison procedure are frozen and the counterfactual never mutates canonical stored labels.

## RQ3 — Reproducibility through provenance

| Element | Trace |
|---|---|
| Phenomenon | reproducibility from preserved provenance |
| Constructs | deterministic reproduction; temporal replication; hidden-state dependence |
| Primary variables | Git commit; dataset hash; protocol/schema/detector/profile/handbook versions; configuration; artifact hash; runtime information |
| Existing canonical definitions | `reproducibility.md`, ResearchRun schema/model, versioning docs |
| Existing data producers | ResearchRun manifest; immutable artifact store; report/export pipeline |
| Existing validation path | PoC replication package and clean-checkout acceptance |
| Governing issues | #86, #87; publication package #199; independent non-implementer reproduction #200 |
| Required analysis | compare expected deterministic hashes/outputs; document expected live-Web differences; record undocumented setup/deviations |
| Planned outputs | acceptance report; replication-package manifest; independent reproduction report |
| Manuscript section | Results — RQ3 |
| Main validity threats | researcher-as-reproducer bias; live-Web temporal instability; unpublished restricted artifacts |

### RQ3 acceptance evidence

A successful primary reproduction by the implementer is necessary but not sufficient for the strongest claim. The manuscript must distinguish:
- repository/CI reproduction;
- clean-checkout reproduction;
- independent reproduction by a non-implementer.

## RQ4 — Measurement validity

| Element | Trace |
|---|---|
| Phenomenon | validity/reliability of automated evidence classification |
| Constructs | human-reference reliability; detector error; abstention/coverage |
| Primary variables | automated state; human state; evidence type; reviewer id/type; rationale; adjudicated state |
| Existing canonical definitions | `annotation-handbook.md`, `evaluation.md`, `gold-dataset.md` |
| Existing commands | `review:sample`, `review:html`, `review:import`, `review:agreement`, `review:evaluate` |
| Existing metrics | observed agreement; Cohen's kappa per signal; TP/FP/TN/FN; precision; recall; F1; support; coverage/abstention |
| Pre-evaluation gate | reviewer instrument pilot/freeze #195 |
| PoC handoff | #178 |
| Final sample design | #196 |
| Human gates | #32, #33, #84 |
| Required analysis | agreement before adjudication; disagreement categorization; final adjudicated reference; per-signal detector error analysis |
| Manuscript section | Results — RQ4 |
| Main validity threats | small/imbalanced classes; reviewer ambiguity; leakage from tuning data; prevalence effects on kappa |

### RQ4 acceptance evidence

No final detector-validity claim is allowed before:
- the final evaluation partition is frozen;
- genuine human reviewers complete independent passes;
- agreement is frozen before adjudication;
- final labels are auditable;
- per-signal support is reported.

## Conditional RQ5 — Transferability

| Element | Trace |
|---|---|
| Phenomenon | transfer of measurement semantics across populations |
| Constructs | semantic stability; context dependence; protocol adaptation |
| Primary variables | same core variables as RQ1/RQ2 plus context/population identifier and protocol version |
| Governing epic/issues | #183, #201 |
| Start gate | central research model + stable human-reference evaluation + stable protocol |
| Required analysis | compare protocol deviations, attrition and observability across populations |
| Planned outputs | second-population ResearchRun; deviation log; comparative results |
| Manuscript section | Results — RQ5 or Future Work, depending completion |
| Main validity threats | convenience population; construct drift; protocol tuning after first case |

---

# Supporting analyses that are not first-paper RQs

These remain important but should not compete with the central manuscript story.

## Resource-type characterization
Supports RQ1 and external-validity interpretation. It is not a standalone paper RQ unless resource taxonomy itself becomes the research contribution.

## Regulatory-profile mapping
Provides domain interpretation of generic evidence. It remains a project/program question (PRQ5), but the first manuscript should avoid making profile coverage a competing central contribution.

## Acquisition/resource cost
Supports engineering evaluation and potential product/service planning. It remains PRQ7 and can be reported as implementation/evaluation context or a separate study.

## PHP-native implementation
PHP is a deliberate engineering choice. The current first paper does not ask whether PHP is superior to Python. Claims should be limited to demonstrated capability of the implemented stack unless a comparative language/runtime study is separately designed.

## ML, clustering and drift
Supervised ML may be evaluated within RQ4 when compared on the same frozen reference data. Unsupervised clustering (#112) and longitudinal drift/anomaly work (#113) are explicitly post-PoC research concepts.

---

# Evidence-status convention

For every RQ, manuscript drafting and issue completion should use:

- **implemented** — the software/data producer exists;
- **collected** — required observations have been produced;
- **human-gated** — genuine human input is still required;
- **analyzed** — the declared analysis has been executed;
- **replicated** — required reproduction/replication has been executed;
- **manuscript-ready** — result can be stated with traceable evidence.

The existence of code is not equivalent to an answered RQ.

## Current status at model version 0.1.0

| RQ | Implemented | Collected | Human-gated | Analyzed | Manuscript-ready |
|---|---:|---:|---:|---:|---:|
| RQ1 | partial | yes (IPB PoC/full runs exist) | no | partial | no |
| RQ2 | implemented analysis path | partial | depends on reviewed outcomes selected | no final run | no |
| RQ3 | strong | yes | independent non-implementer later | partial | no |
| RQ4 | strong machine workflow | PoC candidate set yes | **yes** | no final evaluation | no |
| RQ5 | generic engine exists | no designated second study | possibly | no | no |

This table is a planning status, not a research result.

# Change control

- Research-program questions can expand as the program evolves, but historical protocol/run semantics remain versioned.
- First-paper RQs must be frozen before their final analyses are executed.
- If an RQ changes its construct or denominator after inspecting final results, document the change and treat the previous analysis as exploratory.
- New commands/metrics created to answer an RQ must link back to this model and to their governing issue.
