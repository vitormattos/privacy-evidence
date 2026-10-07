# Beyond the Measurable Web: Preserving Measurement Attrition and Evidence Provenance in Empirical Privacy Research

> Working manuscript. Results remain placeholders until the corresponding frozen analyses are complete.

## Abstract

### Context
Web-based empirical studies may begin from a declared population but ultimately analyze only the subset that survives normalization, deduplication, network acquisition, browser execution and analytical classification. If those losses are not represented explicitly, non-observation can be confused with absence and the final analytical population may become difficult to reconstruct.

### Objective
This study evaluates whether explicitly preserving population transformations, measurement losses, uncertainty states and evidence provenance improves the auditability and reproducibility of large-scale web measurement.

### Method
We use Privacy Evidence, an open-source research instrument, in a real-world case derived from public digital resources declared by churches of the Igreja Presbiteriana do Brasil. The study follows an Engineering Research / Design Science framing, uses Goal–Question–Metric for operationalization, treats automated detectors as measurement instruments validated against independent human reference labels, and distinguishes deterministic reproduction from live-Web replication.

### Results
**Pending.** Populate only from the frozen outputs linked in `evidence-map.md`.

### Conclusions
**Pending.** Do not infer conclusions from implementation completeness.

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
Discuss large-scale browser/web measurement and OpenWPM.

### 2.2 Privacy evidence collection
Discuss WEC/WEC Online and the distinction between evidence collection and legal compliance.

### 2.3 Public privacy inspectors and batch platforms
Discuss Blacklight/Blacklight Query, PrivacyScore, Webbkoll and GDPR Observer.

### 2.4 Reproducibility in empirical software engineering
Connect ResearchRun provenance and replication-package design to empirical-SE reproducibility literature.

### 2.5 Human annotation and measurement validity
Position independent annotation, reliability, adjudication and detector evaluation.

### 2.6 Research gap synthesis
Use the novelty audit. Do not claim undocumented absence in comparison systems.

---

## 3. Study design

### 3.1 Research paradigm
Engineering Research / Design Science is used to create and evaluate the Privacy Evidence artifact. GQM operationalizes goals into questions and metrics. The first empirical context is observational.

Canonical methodology: `docs/research/methodology.md`.

### 3.2 Research object
Publicly observable privacy evidence exposed by digital resources under the declared protocol.

The study does not measure organization-wide legal compliance.

### 3.3 Context and source population
Describe the IPB source, the relationship to the 2025 monograph and the exact frozen dataset/ResearchRun used by the paper.

**Evidence placeholder:** exact paper ResearchRun and source hash must be frozen before submission.

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
Define stage semantics using `measurement-attrition.md` after issue #192 merges.

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

**Do not fill manually from memory.**

Required generated inputs:
- `attrition-results.json`;
- `attrition-summary.json`;
- `attrition-flow.md`;
- failure-reason tables.

Report:
- source population;
- normalized resources;
- eligible resources;
- canonical web units;
- fully/partially observed units;
- non-measurable units;
- causes of loss.

Answer RQ1 explicitly at the end of the subsection.

### 4.2 RQ2 — Analytical impact of missingness

Required output from #193.

Report each selected evidence signal under the three declared analysis conditions and identify which interpretations change or remain robust.

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
Interpret RQ1/RQ2 without claiming causality beyond the design.

### 5.2 Unknown is not zero
Discuss whether the empirical sensitivity analysis supports, limits or rejects the practical importance of this distinction.

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
Address detector error, denominator choices, class imbalance, statistical assumptions and analysis choices.

### 6.3 External validity
The first population is contextual. Generalization depends on analytical reasoning and, if completed, second-population evidence.

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

Answer only the frozen RQs supported by completed evidence.

Do not use implementation breadth as a substitute for empirical results.

---

## Acknowledgements

Use only factual acknowledgements appropriate to the final venue. The historical and institutional origin of the IPB case belongs primarily in the context/motivation section, not as an advocacy objective.

## References

Build from the canonical bibliography/primary sources and include persistent identifiers where available.
