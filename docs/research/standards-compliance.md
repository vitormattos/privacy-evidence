# ACM SIGSOFT Empirical Standards compliance matrix

Assessment version: **0.1.0**
Assessment date: **2026-10-07**

This matrix uses the ACM SIGSOFT Empirical Standards as an author/reviewer checklist. It is a project planning instrument, not a claim of certification.

Primary source:
- https://www2.sigsoft.org/EmpiricalStandards/

Relevant standards/supplements for the current first-paper design:
- General Standard;
- Engineering Research (AKA Design Science);
- Sampling Supplement;
- Case Study / contextual empirical reporting where applicable;
- benchmarking principles for the WEC comparison;
- data-science guidance when ML results are reported.

Legend:
- **Complete** — current repository evidence satisfies the item for the intended study stage.
- **Partial** — substantial evidence exists but a required execution/result is missing.
- **Missing** — not yet satisfied.
- **Conditional** — applies only if that analysis enters the final manuscript.
- **N/A** — not applicable to the declared study.

## General Standard

| Expected attribute | Status | Repository evidence | Gap / action |
|---|---|---|---|
| Clear purpose/problem/RQ | Complete | `research-contribution.md`, `research-model.md` | keep synchronized with manuscript |
| Motivation/importance | Complete | 2025 legacy origin + contribution model | strengthen related-work gap using #189/#190 |
| Key concepts defined | Complete | protocol, variables, data dictionary | audit manuscript terminology |
| Methodologies named | Complete | `methodology.md` | none |
| Methodology appropriate to questions | Partial | method-to-RQ map | empirical results still pending |
| Data collection described in detail | Partial | protocol, acquisition architecture, ResearchRun | manuscript must bind exact run/config |
| Data analysis described in detail | Partial | metrics/evaluation/research-model + frozen #193 result | #194 and remaining RQ analyses still pending |
| Results directly address RQs | Missing | — | execute analyses + human gate |
| Statistical assumptions validated | Conditional | not yet applicable/frozen | required for #194/inferential analyses |
| Implications discussed | Missing | — | manuscript discussion after results |
| Major limitations disclosed | Partial | `validity-threats.md` | update after analyses/replication |
| Conclusions linked to explicit evidence | Missing | — | manuscript result/evidence map #197 |

## Engineering Research / Design Science

| Expected attribute | Status | Repository evidence | Gap / action |
|---|---|---|---|
| Proposed artifact described in adequate detail | Complete | README, architecture docs, ADRs, source/tests | keep paper description concise |
| Need/usefulness/relevance justified | Complete | 2025 reconstruction problem + contribution statement | related-work gap still under #190 |
| Conceptual strengths/weaknesses/limitations evaluated | Partial | validity threats, design boundaries, ADRs | synthesize in manuscript |
| Artifact empirically evaluated in relevant context | Partial | IPB runs/PoC exist | finish human review + attrition analyses |
| Artifact/source available | Complete | public repository | publication release/DOI still pending |
| Evaluation data/package available where permitted | Partial | exports/workflows exist | #199 publication package |
| Theory/methodological basis explicit | Complete | GQM + Engineering Research/Design Science + measurement/reproducibility model | cite primary literature in paper |
| Evaluation is not only toy/synthetic | Complete | real IPB population | second population remains future/conditional |
| Comparison/baseline where useful | Partial | related-work review | execute WEC baseline #191 |

## Sampling Supplement

| Expected attribute | Status | Repository evidence | Gap / action |
|---|---|---|---|
| Sampling/population goal explained | Complete | source population + research model | none |
| Sampling/filtering strategy described | Complete | source adapter, legacy mapping, protocol | #192 must make transitions explicit |
| Selection rationale stated | Complete | IPB historical origin/context | discuss frame limitations |
| Population/sample size reported | Complete for PoC/current runs | workflow/report outputs | final manuscript must pin exact run |
| Stratification rationale for human sample | Partial | `gold-dataset.md`, sampler | #196 publication-grade sizing |
| Filtering/exclusions traceable | Partial | explicit states exist | #192 reconciliation/flow analysis |
| Representativeness not assumed from randomness | Complete | methodology/validity docs | maintain in manuscript |
| Potential frame/selection bias evaluated | Partial | validity threat identified | #194 analysis |

## Human-reference / annotation quality

This section is project-specific and supports the General/Engineering Research expectations for measurement validity.

| Requirement | Status | Evidence | Gap |
|---|---|---|---|
| Versioned handbook | Complete | `annotation-handbook.md` | freeze exact version for final sample |
| Reviewer-neutral equivalent packets | Implemented | review workflow/tests | #195 pilot and #178 PoC handoff |
| Detector state hidden | Implemented | reviewer UI/tests | verify in frozen packet |
| Live-site observation excluded from primary pass | Implemented after PR for #195 | reviewer UI | real pilot still required |
| Independent actual humans | Missing | explicit human gate | #32/#33/#84 |
| Agreement before adjudication | Implemented workflow | `review:agreement` | execute after humans |
| Pre-adjudication labels immutable | Complete in design/tests | storage workflow | verify real run |
| Final sample sized for claims | Missing | — | #196 |
| Detector metrics per signal | Implemented workflow | `review:evaluate`, metrics docs | execute on final reference |

## Reproducibility / open-science expectations

| Requirement | Status | Evidence | Gap |
|---|---|---|---|
| Code revision recorded | Complete | ResearchRun |
| Dataset hash recorded | Complete | ResearchRun |
| Protocol/schema/profile versions recorded | Complete/Partial by component | ResearchRun/versioning | ensure all final versions frozen |
| Artifact hashes preserved | Complete | artifact store/data dictionary |
| Exact commands documented | Partial | workflows/docs | #199 publication package |
| Clean-checkout reproduction | Partial | PoC acceptance path | #87 |
| Independent non-implementer reproduction | Missing | — | #200 |
| Public replication package | Partial | export machinery | #199 |
| Persistent identifier/DOI | Missing | — | #199 |
| Citation metadata | Partial | CITATION.cff exists | #198 |

## Related work / novelty quality

| Requirement | Status | Evidence | Gap |
|---|---|---|---|
| Scholarly prior work synthesized | Partial | `literature-review.md` | expand with #189/#190 |
| Competing tools characterized | Partial | #189 branch/review | merge #189 and audit #190 |
| Unsupported “first/best” claims prohibited | Complete | contribution model | enforce in manuscript |
| External baseline experiment | Missing | — | #191 |

## RQ readiness

| Paper RQ | Current standard-readiness | Main blockers |
|---|---|---|
| RQ1 measurement attrition | Partial | #192 |
| RQ2 missingness impact | Analyzed for frozen IPB run | detector-validity caveat remains under RQ4 |
| RQ3 reproducibility | Partial | #86/#87/#199/#200 |
| RQ4 detector validity | Human-gated | #195/#178/#196/#32/#33/#84 |
| RQ5 transferability | Conditional/not started | #201 |

## Current highest-priority standard gaps

1. Complete #192 so population/filtering/attrition transitions are fully reportable.
2. Complete #195 and genuine human annotation before making detector-validity claims.
3. Complete #196 so final evaluation support matches intended performance claims.
4. Execute #194 with predeclared analysis choices and statistical-assumption checks; #193 is frozen for the 2026-10-05 IPB run.
5. Complete clean and independent reproduction (#87/#200).
6. Complete novelty audit/baseline (#190/#191).
7. Freeze publication package and scholarly metadata (#198/#199).

## Review rule

Before manuscript submission, rerun this checklist against the exact submitted manuscript and frozen replication package. A repository capability counts as evidence only if the manuscript's declared study actually used it.
