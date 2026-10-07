# Novelty audit and comparative capability matrix

Audit version: **0.1.0**
Evidence cutoff: **2026-10-07**

Source review: `tool-landscape-review.md`.

## Purpose

This document converts the focused landscape review into a claim-control artifact.

Every candidate novelty claim is classified as:
- **safe** — supported by current reviewed evidence and project implementation;
- **unsafe** — contradicted by existing prior art or too broad;
- **unresolved** — plausible, but requires stronger documentation review or empirical comparison;
- **project capability, not novelty** — true of Privacy Evidence but not itself a scientific novelty claim.

No claim marked unresolved may appear in a manuscript abstract/title as established novelty.

## Comparison dimensions

| Dimension | Privacy Evidence | WEC/WEC Online | OpenWPM | Blacklight Query | PrivacyScore | GDPR Observer | Claim status |
|---|---|---|---|---|---|---|---|
| browser-based privacy measurement | documented | documented | documented | documented | documented | via WEC | unsafe as novelty |
| cookies / third-party requests | documented | documented | documented | documented | tracker-oriented | via WEC | unsafe as novelty |
| URL-list / batch processing | documented | unclear in standalone WEC docs | documented by study scripts/framework use | documented | documented | documented | unsafe as novelty |
| public/hosted scan interface | concept only | WEC Online documented | not default | documented | documented | service-oriented | unsafe as novelty |
| periodic rescanning | implemented infrastructure possible | unclear | study-defined | repeatable | documented | documented | unsafe as novelty |
| machine-readable evidence | documented | documented | documented | documented outputs | documented platform data | documented raw/API data | unsafe as novelty |
| source-frame preservation before crawl | documented | unknown | study-defined/unknown | input-list only in reviewed docs | list-oriented | collections documented | unresolved |
| explicit stage-by-stage population attrition | planned #192 | unknown | failure/status logging but not same semantics | unknown | unknown | collection/test identity but unclear attrition semantics | unresolved |
| semantic distinction absent/unknown/unavailable/invalid/excluded/not-applicable | documented | unknown | command/failure semantics differ | unknown | unknown | unknown | unresolved |
| source -> normalized -> deduplicated -> canonical measurement-unit provenance | documented | unknown | study-defined | unknown | unknown | collection-oriented, unclear canonicalization | unresolved |
| exact artifact hashes linked to evidence | documented | evidence outputs exist; hash semantics unclear | storage/logging documented; exact equivalent unclear | output artifacts | unknown | evidence paths/ids | unresolved |
| independent reviewer-neutral packets | documented | unknown | not framework default | unknown | unknown | curation exists | unresolved |
| agreement computed before adjudication | documented workflow | unknown | study-specific | unknown | unknown | unknown | unresolved |
| detector evaluation vs adjudicated human reference | documented workflow; final execution pending | unknown | study-specific | unknown | unknown | unknown | unresolved |
| generic evidence separated from regulatory profile | documented | WEC collects evidence; mapping model unclear | N/A | N/A | N/A | compliance-check layer exists | unresolved |
| one run binds dataset + code + protocol + schema + profile + handbook + config versions | documented | unknown | strong config/version guidance, but exact bundle differs | unknown | unknown | test/date identity documented | unresolved |
| missingness sensitivity analysis as first-class result | planned #193 | not documented in reviewed sources | study-specific | not documented | not documented | not documented | unresolved/scientific study claim |
| independent clean-room reproduction package | designed #87/#199/#200 | reproducible claim documented | versioning/reproducibility guidance documented | code/output reproducible in principle | open source | open source | unsafe if stated generically; unresolved for exact protocol |

## Claims that are unsafe

Do **not** claim that Privacy Evidence is:
- the first open-source privacy scanner;
- the first reproducible website privacy-measurement tool;
- the first browser-based privacy inspection tool;
- the first tool to collect cookies or third-party requests;
- the first tool to analyze lists of websites;
- the first privacy tool to support repeated/longitudinal scans;
- the first public URL-based privacy inspector;
- the first system to combine WEC-like evidence with population collections;
- inherently more accurate than WEC/OpenWPM/Blacklight/PrivacyScore;
- legally authoritative because it uses versioned regulatory profiles.

## Claims that are currently safe

The following are safe **descriptive capability claims**, provided they are scoped to Privacy Evidence itself:
- the project preserves source values before normalization;
- the project represents unknown/unavailable/invalid/excluded/not-applicable explicitly rather than collapsing all non-positive states;
- the project records ResearchRun-level provenance across dataset/code/protocol/configuration;
- the project separates generic evidence from regulatory-profile interpretation;
- the project has an auditable independent-human-review/adjudication workflow;
- the project can compute per-signal detector metrics once a human reference is available.

These are not yet all safe as **novelty claims**.

## Candidate scientific novelty claims

### C1 — Population-accounting semantics

Candidate claim:
> Privacy Evidence treats the transformation from declared source population to measurable analytical population as explicit research data.

Status: **unresolved but strong**.

Required evidence:
- #192 stage-by-stage attrition output;
- #190 documentation evidence showing whether comparison tools expose equivalent semantics;
- #191 WEC comparison where documentation is insufficient.

### C2 — Measurement-loss semantics

Candidate claim:
> Privacy Evidence preserves non-observability as a distinct measurement state and evaluates the analytical effect of alternative missingness treatments.

Status: **unresolved as novelty; testable as study contribution**.

Required evidence:
- #193 sensitivity analysis;
- evidence that compared tools/studies do not already operationalize the same end-to-end state model, or careful wording that avoids uniqueness.

Important distinction:
The study can contribute an **empirical finding about measurement attrition** even if another tool has similar state semantics.

### C3 — End-to-end evidence chain

Candidate claim:
> Privacy Evidence preserves an auditable chain from source population through acquisition artifact, automated detector output, independent human review/adjudication and regulatory interpretation.

Status: **unresolved**.

Required evidence:
- final #5 human workflow execution;
- comparison audit of WEC/GDPR Observer documentation;
- #191 artifact-level comparison.

### C4 — ResearchRun provenance bundle

Candidate claim:
> A ResearchRun binds code, dataset, protocol, schema, detector/profile/handbook versions and measurement-affecting configuration into one reproducibility unit.

Status: **safe as artifact design claim; unresolved as novelty claim**.

OpenWPM already documents strong experiment-versioning and logging practices, so the contribution must be stated as the specific bundle/semantics implemented here, not “versioning for web measurement”.

### C5 — Detector validation integrated with research provenance

Candidate claim:
> Detector outputs are evaluated as measurement instruments against independently adjudicated human references while preserving detector and artifact provenance.

Status: **safe as intended design, not yet a completed empirical contribution**.

Required evidence:
- #195/#196;
- genuine reviewers #32/#33;
- final evaluation #84.

## Novelty claim decision rules

A claim may be promoted from unresolved to manuscript-safe only if one of these is true:

1. **Documentation-supported distinction**
   - primary sources for relevant comparison tools are sufficiently explicit;
   - the difference is substantive, not wording.

2. **Empirical distinction**
   - a controlled baseline experiment uses comparable inputs/configuration;
   - the result demonstrates a defined difference relevant to an RQ.

3. **Contribution without uniqueness**
   - the claim is framed as an evaluated method/result, not “first” or “unique”.

The preferred strategy is usually #3. A paper does not need to be the first artifact with every capability if it contributes new evidence or a new methodological finding.

## WEC baseline questions for #191

The WEC comparison should freeze and test a narrow set of questions:

1. Can both tools account for the same input list and explicitly report failed/non-observable sites?
2. What artifact/provenance identifiers are preserved for a scan?
3. How are cookies/third-party request evidence represented?
4. How are unavailable/error cases represented?
5. Can outputs be deterministically tied to configuration/tool version?
6. Which information is needed to reconstruct why one input site did or did not reach the analytical result?
7. What additional processing would be needed to create a human-review/evaluation dataset from each tool's outputs?

The experiment must not ask “which tool is better?” in general.

## Manuscript wording guidance

Prefer:
- “We evaluate a provenance-aware measurement workflow that...”
- “Our implementation explicitly represents...”
- “In the studied population, preserving measurement attrition changed/did not change...”
- “Compared with WEC under the declared setup, we observed...”
- “Existing systems already provide browser-based privacy evidence collection; our study focuses on...”

Avoid:
- “Unlike all existing tools...”
- “the first...”
- “state-of-the-art” without a defined benchmark;
- “more accurate” without a frozen human reference and baseline;
- “compliance scanner” as shorthand for the scientific artifact.

## Current novelty posture

The strongest defensible current positioning is:

> Existing tools already support privacy-oriented web measurement at single-site and population scales. Privacy Evidence therefore does not claim novelty in automated privacy scanning itself. The research program instead evaluates whether explicit population accounting, measurement-loss semantics, end-to-end evidence provenance and human-validated classification improve the auditability and interpretability of empirical web studies.

This statement remains subject to #191 and the empirical analyses required by the paper RQs.
