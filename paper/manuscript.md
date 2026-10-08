<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Beyond the Measurable Web: Preserving Measurement Attrition and Evidence Provenance in Empirical Privacy Research

> Working manuscript. RQ1 and RQ2 results below are populated from frozen repository evidence. RQ3 and RQ4 remain incomplete and must not be inferred from implementation status.

## Abstract

### Context
Web-based empirical studies may begin from a declared population but ultimately analyze only the subset that survives normalization, deduplication, network acquisition, browser execution and analytical classification. If those losses are not represented explicitly, non-observation can be confused with absence and the final analytical population may become difficult to reconstruct.

### Objective
This study evaluates whether explicitly preserving population transformations, measurement losses, uncertainty states and evidence provenance improves the auditability and reproducibility of large-scale web measurement.

### Method
We use Privacy Evidence, an open-source research instrument, in a real-world case derived from public digital resources declared by churches of the Igreja Presbiteriana do Brasil. The study follows an Engineering Research / Design Science framing, uses Goal–Question–Metric for operationalization, treats automated detectors as measurement instruments validated against independent human reference labels, and distinguishes deterministic reproduction from live-Web replication.

### Results
The frozen IPB case contains 2,993 declared source records. Of these, 658 normalize to a URL, 589 are website-measurement eligible references, and deduplication yields 566 canonical website measurement units. Among canonical units, 193 are fully measured, 12 partially measured, and 361 not measurable; therefore 205/566 (36.22%) are analytically observable. Across five predeclared privacy-evidence outcomes, only 264/566 canonical units (46.64%) are resolved. Treating unresolved observations as negative reduces each selected prevalence estimate by 53.36% relative to complete-case analysis. A predeclared selective-measurability analysis found a 9.33 percentage-point descriptive difference between institutional websites and third-party hosted pages, but no inferential support after Holm correction (adjusted p = 0.7373).

### Conclusions
For the frozen IPB observation, population attrition is large enough that intended, normalized, eligible, canonical and analytically observed populations are materially different denominators. Missingness semantics materially change the selected estimates, supporting the rule that non-observation must not be silently interpreted as absence. These findings support P1 and P2 for this case. Claims about deterministic independent reproduction (RQ3), detector validity against human reference labels (RQ4), and transferability beyond the IPB context remain pending.

## Keywords

empirical software engineering; web measurement; privacy; reproducibility; provenance; measurement attrition; research software; open source; human annotation; PHP

---

## 1. Introduction

### 1.1 Problem

Empirical research based on the live Web has a population-accounting problem: the resources a study intends to analyze are not necessarily the resources that become measurable.

Resources can be transformed or lost through:
- malformed or non-HTTP source values;
- protocol eligibility rules;
- normalization and deduplication;
- DNS/TLS/HTTP failure;
- redirects and anti-bot systems;
- browser/resource limits;
- incomplete preserved evidence;
- classification uncertainty.

A study that reports only successfully measured cases can hide those transformations.

### 1.2 Historical origin and motivation

The research problem emerged from a 2025 academic study of public digital resources declared by churches of the Igreja Presbiteriana do Brasil (IPB). That earlier study remains a distinct historical artifact with immutable published results.

A later attempt to reconstruct and extend that work exposed limitations in the preservation of source snapshots, intermediate transformations, acquisition failures and exact analysis provenance. These limitations motivated the development of Privacy Evidence.

The IPB population is therefore the origin of the research problem and the first real empirical context. It is not assumed to represent organizations in general.

### 1.3 Gap

Existing systems already provide important forms of privacy-oriented web measurement, including browser instrumentation, cookies/third-party-request collection, batch processing and repeated scans.

The gap investigated here is narrower: whether an empirical web-measurement workflow can make the transformation from intended population to analytical evidence explicitly auditable, and whether preserving measurement loss changes the interpretation or reproducibility of study results.

The final gap statement must be reconciled with:
- `docs/research/tool-landscape-review.md`;
- `docs/research/novelty-audit.md`;
- the WEC baseline experiment in issue #191.

### 1.4 Contributions

This paper intends to contribute:

1. **A population-accounting model** that preserves source-to-measurement transformations rather than silently dropping non-measurable resources.
2. **An empirical analysis of measurement attrition**, including sensitivity to complete-case and naive missing-as-negative treatments.
3. **A provenance-aware research artifact** that binds source data, exact acquired artifacts, code/protocol/configuration versions and analysis outputs.
4. **A measurement-validity evaluation** of automated privacy-evidence detectors against independently adjudicated human reference labels.

A fifth contribution—cross-population transferability—is included only if the second-population study is completed before manuscript freeze.

These are intended contributions, not completed findings. Their final wording must follow the evidence status in `evidence-map.md`.

### 1.5 Research questions

**RQ1 — Measurement attrition.** How does a declared web population transform into an effectively measurable population under a reproducible acquisition protocol, and what factors account for measurement loss?

**RQ2 — Analytical impact of missingness.** How does explicitly representing non-observability and measurement failures affect denominators, estimates and interpretation compared with complete-case analysis and a naive missing-as-negative treatment?

**RQ3 — Reproducibility through provenance.** To what extent can the analytical population and deterministic derived findings be independently reproduced from preserved inputs, artifacts, protocol versions and configurations?

**RQ4 — Measurement validity.** How reliably do automated privacy-evidence detectors reproduce independently adjudicated human classifications on a frozen evaluation dataset?

**Conditional RQ5 — Transferability.** To what extent can the same core measurement protocol be applied to a meaningfully different web population without changing its measurement semantics?

---

## 2. Background and related work

### 2.1 Empirical web measurement
Privacy-oriented web measurement is already supported by mature research frameworks. OpenWPM provides Firefox/Selenium-based browser instrumentation, structured collection of network and JavaScript telemetry, multi-browser orchestration, command/status logging, versioned releases and containerized execution. Its existence rules out broad novelty claims based on large-scale automated browser measurement, structured privacy telemetry, process orchestration or research-oriented versioning alone.

### 2.2 Privacy evidence collection
The European Data Protection Supervisor's Website Evidence Collector (WEC) is particularly close to the evidence-acquisition domain of this work. WEC uses Chromium with a fresh browser profile and records screenshots, links, local storage, cookies and third-party requests in human- and machine-readable forms. WEC therefore constitutes prior art for reproducible browser-based privacy evidence collection and is the primary external baseline selected for issue #191.

Privacy Evidence does not treat browser-observable evidence as a legal-compliance verdict. Its construct is narrower: evidence that was observable under a declared, versioned measurement protocol. Regulatory interpretation is kept separate from the generic evidence state.

### 2.3 Public privacy inspectors and batch platforms
Blacklight demonstrates real-time headless-browser privacy inspection, while Blacklight Query extends the collector to URL-list batch operation. PrivacyScore provides list comparison and repeated rescanning. Webbkoll is prior art for public-facing single-site privacy checks. GDPR Observer is especially relevant because it combines WEC with curated website collections, repeated population-level collection and APIs.

These systems rule out novelty claims based merely on URL scanning, headless-browser inspection, batch operation, repeated scans, public privacy reports or open-source implementation.

### 2.4 Reproducibility in empirical software engineering
The methodological stack follows Engineering Research / Design Science and GQM, with reproducibility treated as an empirical property rather than an implementation attribute. The project distinguishes deterministic reproduction from live-Web replication: deterministic outputs should be regenerable from the same preserved inputs, code, protocol and configuration, while repeated acquisition of live pages is expected to vary over time.

The ResearchRun abstraction records code revision, source-data hash and protocol/configuration identity and links derived outputs to preserved artifacts. Whether this provenance is sufficient for independent clean-environment reproduction remains an empirical question governed by RQ3.

### 2.5 Human annotation and measurement validity
Automated detectors are treated as measurement instruments rather than unquestioned classifiers. The planned validity procedure uses reviewer-neutral preserved-evidence packets, at least two genuine independent human reviewers, agreement before adjudication, immutable pre-adjudication labels and a separate adjudicated reference. Detector precision, recall, F1, support and abstention/coverage are reported per signal only after this human-reference process is complete.

### 2.6 Research gap synthesis
The related-work audit does not support claims that Privacy Evidence is the first privacy scanner, the first reproducible web collector, the first batch privacy-analysis tool or the first population-oriented WEC derivative.

The narrower gap investigated here concerns the research semantics around end-to-end population accounting and measurement loss: preserving source-to-measurement transformations, keeping absent/unknown/unavailable/invalid/excluded/not-applicable states distinct, binding evidence and analytical decisions to run-level provenance, and testing how those semantics alter empirical conclusions. Documentation review leaves some of these distinctions unresolved across existing tools; the controlled WEC comparison in #191 is therefore treated as supporting evidence rather than assumed novelty.

---

## 3. Study design

### 3.1 Research paradigm
Engineering Research / Design Science is used to create and evaluate the Privacy Evidence artifact. GQM operationalizes goals into questions and metrics. The first empirical context is observational.

Canonical methodology: `docs/research/methodology.md`.

### 3.2 Research object
Publicly observable privacy evidence exposed by digital resources under the declared protocol.

The study does not measure organization-wide legal compliance.

### 3.3 Context and source population
The first empirical context is a source population derived from public digital resources declared by churches of the Igreja Presbiteriana do Brasil (IPB). This population is used because it is the historical origin of the research problem, not because it is assumed to represent organizations generally.

The frozen full-population observation used for RQ1/RQ2 is ResearchRun `01a10a29-cf8d-7711-8e6d-ec954e592cc2`, produced by GitHub Actions run `37260583969` from Git commit `fa276f203ba6d95bfb0270b7fdfd71047d1c47f1`. The source dataset SHA-256 is `094e991b22293d78a11bc18ec2fb191d612b66ecad65145466d22108db4bf022`; protocol version is `0.1.0-draft`.

The historical 2025 study remains a distinct artifact. Current protocol semantics are not retroactively projected onto its published results.

### 3.4 Units of analysis
Use the protocol definitions:
- entity;
- declared resource;
- normalized resource;
- canonical website measurement unit;
- fetched document/artifact;
- evidence item;
- ResearchRun.

### 3.5 Privacy Evidence artifact
Summarize only architecture needed to understand the study:
- source adapter/import;
- normalization/classification;
- bounded HTTP acquisition;
- adaptive browser escalation;
- immutable artifact store;
- evidence detectors;
- human-review workflow;
- regulatory profiles;
- analysis/export.

### 3.6 PHP implementation choice
State PHP as a deliberate research-software engineering choice, not a language-superiority hypothesis.

Document the modern PHP stack, static analysis, tests, ML integration and containerization as evidence that the implemented artifact supports the required workflow. Do not claim parity/superiority to Python without a comparative experiment.

### 3.7 Population accounting and missingness
Population accounting preserves the declared source frame and distinguishes source records, normalized resources, website-measurement eligible references, canonical website measurement units and analytically observed units.

For the historical frozen run, the dedicated attrition export did not yet exist. RQ1 is therefore reconstructed deterministically from the preserved complete `population-results.json` using the same rules now implemented by `RunExporter`. The reconstruction is hash-bound and does not recollect the live Web.

Missingness sensitivity is evaluated under three frozen conditions: complete-case analysis, an intentionally naive counterfactual that treats unresolved measurements as negative for analysis only, and the canonical provenance-aware treatment that preserves unresolved state and reports identification bounds.

### 3.8 Human-reference protocol
Describe:
- frozen final evaluation partition;
- reviewer-neutral packets;
- preserved evidence only;
- two independent humans;
- agreement before adjudication;
- immutable pre-adjudication labels;
- adjudicated reference;
- detector evaluation.

Do not call AI instances human reviewers.

### 3.9 Analysis procedure by RQ

#### RQ1
Use attrition results/summary and failure taxonomy.

#### RQ2
Compare complete-case, intentionally naive missing-as-negative counterfactual and canonical provenance-aware semantics.

#### RQ3
Use clean-checkout and independent reproduction evidence.

#### RQ4
Report agreement plus per-signal confusion matrices, precision, recall, F1, support and coverage.

#### RQ5
Only if #201 is complete.

### 3.10 Open science and reproducibility
Describe software release, protocol, hashes, replication package, data restrictions and persistent identifiers.

---

## 4. Results

### 4.1 RQ1 — Measurement attrition

The frozen source population contains 2,993 records. Of these, 658 normalize to a URL and 2,335 terminate as normalization-unavailable. Among normalized resources, 589 references are website-measurement eligible and 69 are protocol-excluded. Deduplication reduces the 589 eligible references to 566 canonical website measurement units, with 23 eligible references represented as duplicates of another canonical unit.

At the canonical-unit level, 193 units are fully measured, 12 partially measured and 361 not measurable. The analytically observed population is therefore 205/566 canonical units (36.22%); the measurement-loss population is 361/566 (63.78%).

The terminal source-record accounting reconciles exactly:

```text
2,335 normalization_unavailable
+ 69 protocol_excluded
+ 23 duplicate_eligible_reference
+ 193 fully_measured
+ 12 partially_measured
+ 361 not_measurable
= 2,993 source records
```

The 361 non-measurable canonical units also preserve a primary failure reason. DNS failure is the largest category (228), followed by anti-bot challenges (47), HTTP 429 responses (34), HTTP 404 responses (13), TLS failure (11), no successful document (7), HTTP 403 (5), HTTP 500 (4), timeout (4), private-network classification (2), and six one-case categories.

**RQ1 answer.** The declared population and the analytically measurable population are not interchangeable. In this observation, substantial loss occurs both before acquisition (normalization/protocol transitions) and during measurement. Explicit stage and terminal-state accounting is required to reconstruct which population each result describes. This supports P1 for the frozen IPB run, without implying that the same attrition pattern generalizes to another population or date.

### 4.2 RQ2 — Analytical impact of missingness

Five outcomes were frozen before this sensitivity result was inspected: privacy notice, privacy-law reference, privacy contact, rights disclosure and cookie notice. For all five outcomes, 264 of 566 canonical units are resolved and 302 remain unresolved, giving 46.64% resolved coverage.

| Outcome | Complete-case prevalence | Naive missing-as-negative | Absolute change | Relative change | Provenance-aware bounds |
| --- | ---: | ---: | ---: | ---: | ---: |
| Privacy notice | 15.53% | 7.24% | -8.29 pp | -53.36% | 7.24%–60.60% |
| Privacy-law reference | 6.82% | 3.18% | -3.64 pp | -53.36% | 3.18%–56.54% |
| Privacy contact | 3.79% | 1.77% | -2.02 pp | -53.36% | 1.77%–55.12% |
| Rights disclosure | 4.17% | 1.94% | -2.22 pp | -53.36% | 1.94%–55.30% |
| Cookie notice | 15.15% | 7.07% | -8.08 pp | -53.36% | 7.07%–60.42% |

All five predeclared outcomes are unstable across the declared missingness treatments. The identical 53.36% relative reduction is not five independent effects; it follows from the common 264-resolved/302-unresolved denominator split, while the absolute change varies by signal.

**RQ2 answer.** Missingness semantics materially change the selected estimates in this frozen run. Treating unresolved units as negative reduces every selected prevalence estimate by approximately 53.36% relative to complete-case analysis, while provenance-aware bounds remain wide. P2 is therefore supported for this observation. The result does not identify the missingness mechanism, establish true population prevalence or validate the detectors against human reference labels.

A supporting selectivity analysis found institutional websites measurable in 37.11% of cases versus 27.78% for third-party hosted pages, a descriptive difference of 9.33 percentage points (OR 1.53). The association was not supported after Holm correction (adjusted p = 0.7373). Duplicate-group membership showed little difference (37.50% vs 36.20%; adjusted p = 1.0), and URL scheme could not be tested because all canonical normalized URLs used `http`. These results do not justify assuming missingness is random; they only fail to demonstrate selective measurability with the predeclared predictors available in the frozen export.

### 4.3 RQ3 — Reproducibility through provenance

Required outputs from #87/#199/#200.

Separate:
- deterministic CI/repository reproducibility;
- clean-checkout reproduction;
- independent non-implementer reproduction;
- expected live-Web non-determinism.

### 4.4 RQ4 — Measurement validity

Required outputs from #195/#196/#32/#33/#84.

Report:
- final sample/support;
- reviewer agreement before adjudication;
- disagreement categories;
- adjudicated reference;
- detector metrics by signal;
- representative error classes.

### 4.5 Conditional RQ5 — Transferability

Include only if #201 completes before manuscript freeze.

---

## 5. Discussion

### 5.1 What measurement attrition means for empirical web studies
The IPB case shows that population definition is not a one-time sampling decision. The denominator changes through normalization, protocol eligibility, deduplication and acquisition. Reporting only the 205 observed canonical units would hide that they are survivors of a declared 2,993-record source frame and a 566-unit canonical website population.

The methodological implication is not that every source record should be treated as an analyzable website. Rather, each transformation should remain explicit so that readers can distinguish source-frame limitations, protocol exclusions, duplicate references and technical measurement failures. This also prevents acquisition failure from being misreported as absence of privacy evidence.

### 5.2 Unknown is not zero
The sensitivity analysis provides direct evidence that this distinction matters in the frozen case. When the 302 unresolved canonical units are naively treated as negative, every selected prevalence estimate falls by 53.36% relative to the corresponding complete-case estimate.

The provenance-aware bounds are wide because unresolved coverage is large. That uncertainty is itself part of the result: the measurement procedure does not justify collapsing the unresolved portion of the population into a negative category. The analysis therefore supports preserving non-observation as a distinct state rather than manufacturing apparent certainty through denominator choice.

### 5.3 Artifact design and reproducibility
Relate ResearchRun provenance to observed reproduction results.

### 5.4 Automated privacy evidence as a measurement instrument
Discuss which detector signals are sufficiently reliable and which require human review or abstention.

### 5.5 Relationship to existing tools
Use #191 rather than asserting superiority.

### 5.6 Implications for researchers
Discuss source-frame preservation, failure accounting, denominator semantics and replication packages.

### 5.7 Implications for practitioners
Keep operational privacy diagnostics separate from legal certification.

---

## 6. Threats to validity

### 6.1 Construct validity
Public website evidence is not organization-wide privacy practice.

### 6.2 Internal/conclusion validity
RQ1 uses exact reconciled population accounting for the frozen export. RQ2 is sensitive to denominator semantics by design; outcomes and comparison conditions were frozen before the reported result was inspected. The selective-measurability analysis uses support counts, Fisher exact tests for sparse tables and Holm adjustment for the reported level tests.

The current conclusions remain limited by detector validity because RQ4 human-reference evaluation is not complete. RQ1/RQ2 therefore support claims about measurement states and sensitivity of the automated evidence outputs, not claims that the underlying detectors are already accurate enough for substantive legal or organizational inference.

### 6.3 External validity
The IPB population is contextual and was selected because it generated the original research problem. The observed attrition rates and missingness effects are empirical properties of this frozen observation, not population-independent constants. The current selective-measurability analysis also has limited predictor coverage and small support for some subgroups. Generalization therefore depends on analytical reasoning and, if completed, a second-population study under the same core semantics.

### 6.4 Reliability/reproducibility
Address Web temporal instability, browser environment, provenance completeness and independent reproduction.

### 6.5 Researcher/reviewer bias
Disclose the researcher's relationship to the IPB context and mitigation through explicit protocol, preserved evidence, independent annotation and auditable decisions.

---

## 7. Artifact and data availability

Populate with the exact:
- repository;
- tagged release;
- software DOI;
- paper replication-package DOI;
- protocol version;
- dataset/source identifiers;
- ResearchRun identifier;
- restricted-data statement;
- commands required to reproduce tables/figures.

---

## 8. Ethics and data handling

Explain that the study uses publicly observable web resources while still applying data minimization and restricted handling for raw artifacts that may contain personal data/copyrighted content.

Any future service/customer telemetry is outside this study unless separately approved by a research protocol.

---

## 9. Conclusion

For the frozen IPB observation, the transformation from declared source population to analytical population is large and auditable: 2,993 declared records yield 566 canonical website measurement units, of which 205 (36.22%) are analytically observable. This supports P1's population-accountability claim for the studied run.

The same observation also shows that missingness semantics affect substantive estimates. Across five predeclared evidence outcomes, only 46.64% of canonical units are resolved, and an intentionally naive missing-as-negative treatment reduces the corresponding prevalence estimates by 53.36% relative to complete-case analysis. This supports P2 and provides empirical justification for preserving unknown/unavailable states rather than treating them as negative evidence.

These results establish neither legal compliance nor detector validity, causal missingness mechanisms, independent reproducibility or transferability beyond the IPB case. RQ3 and RQ4 remain open empirical gates, and the WEC comparison remains supporting work rather than a completed superiority claim.

---

## Acknowledgements

Use only factual acknowledgements appropriate to the final venue. The historical and institutional origin of the IPB case belongs primarily in the context/motivation section, not as an advocacy objective.

## References

Build from the canonical bibliography/primary sources and include persistent identifiers where available.
