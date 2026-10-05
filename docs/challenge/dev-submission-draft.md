---
title: I turned my old privacy study into a reproducible research tool for my master's advisor
published: true
tags: devchallenge, weekendchallenge, hf26challenge
---

*This is a submission for the [Hacktoberfest Weekend Challenge: Build for a Friend](https://dev.to/challenges/hacktoberfest-weekend-2026-10-01).*

## Table of Contents

- [What I Built](#what-i-built)
- [Demo](#demo)
- [Research Methodology](#research-methodology)
- [Results from the IPB Analysis](#results-from-the-ipb-analysis)
- [Code](#code)
- [How I Built It](#how-i-built-it)
- [Why Does Open Innovation Matter?](#why-does-open-innovation-matter)

---

## What I Built

I dedicate **Privacy Evidence** to **Igor Scaliante Wiese, my master's advisor at UTFPR and professor of the Free Software Development course**.

A previous academic monograph I wrote at Seminário Simonton explored digital ethics and the effect of digital practices on Christian fellowship. One part of the research looked at privacy and data-protection practices on websites associated with churches of the Igreja Presbiteriana do Brasil (IPB).

The hard part was not only interpreting LGPD-related evidence. It was building the sample itself.

I started from the public IPB directory and worked through entries one by one. Some links were Facebook or Instagram pages. Some were link aggregators. Some domains existed but no longer served a site. Some returned errors. After manually reducing the list to actual websites, I still had to inspect the remaining sites individually.

That process helped answer the research question, but it had a serious empirical-software-engineering problem: too much of the workflow was manual. Another researcher could read the methodology, but could not reliably replay the same sequence of filtering, collection, classification and review from the original source data.

Privacy Evidence is the tool I wanted that study to have, but the current goal is broader: turn the old work into something my advisor can inspect as empirical software-engineering research and that can support the scientific publications I need to produce during the master's program.

Igor did not ask me to build this exact software, and I am not claiming that he has already validated its results. I am dedicating the work to him because his role as my advisor and as a professor of Free Software Development makes him a real academic reference for the kind of research artifact I want this project to become: open, inspectable, reproducible and suitable for scientific scrutiny.

It accepts a population of websites from a source adapter, preserves provenance, acquires public evidence through a repeatable protocol, classifies observable privacy signals, sends ambiguous cases to review, and lets the same generic evidence be evaluated against versioned profiles such as LGPD, GDPR and cookie/ePrivacy requirements.

{% card %}
### Important methodological boundary

Privacy Evidence deliberately does **not** say that a website or organization is legally compliant.

A public website is only one observable slice of a broader privacy program. The project measures **publicly observable evidence**, not organization-wide legal compliance.
{% endcard %}

The immediate problem came from IPB websites, but the architecture is source-agnostic. The same workflow can be used for another denomination, an organization that owns many public sites, or an empirical research population supplied as a list of URLs.

## Demo

The Weekend Challenge demo is deliberately small and reproducible.

From a clean checkout after `composer install`:

```bash
examples/hacktoberfest/run-demo.sh
```

The script builds a local PHP-native Rubix model from deterministic project-authored examples and then runs a separate inference command using the saved model artifact.

The demo uses a controller-identity sentence that the current deterministic rule does not recognize. The local ML model identifies the signal as a candidate. Instead of silently overriding the rule, Privacy Evidence records the disagreement as an auditable `ai_suggestion` and leaves the case pending for human review.

The JSON output includes the deterministic detector state, model probability and threshold, model artifact SHA-256, training provenance, disagreement state and review priority.

Most importantly, the demo **fails if the model artifact is removed**. The ML is not decorative; it is required to produce the demonstrated second opinion.

{% cta https://github.com/vitormattos/privacy-evidence/actions/runs/37265354267 %}
Inspect the successful public Hacktoberfest demo
{% endcta %}

This GitHub Actions run executes the real PHP-native Rubix model build and inference path in public. The successful output shows `aiAtCore: true`, `modelRequired: true`, a `controller_identity` ML candidate with probability `1`, rule/ML disagreement, one pending review case and an auditable `ai_suggestion`.

## Research Methodology

This project is not only a crawler or a classifier. I designed it as a **reproducible empirical software-engineering study**.

The research protocol uses the **Goal–Question–Metric (GQM)** paradigm to connect the research goal, research questions and observable measurements. The reporting and study-design choices also use the **ACM SIGSOFT Empirical Standards** as a methodological reference.

The object of study is deliberately narrow:

> **publicly observable privacy evidence exposed by digital resources**

It is **not** a measurement of organization-wide legal compliance.

### Research design

The study follows seven measurement layers. Each layer preserves the previous observation instead of silently replacing it:

1. **Source observation** — preserve the original value from the source population.
2. **Normalization and classification** — determine whether the source represents an institutional website, social network, third-party page, malformed address or another resource type.
3. **Technical acquisition** — attempt HTTP acquisition and, when justified, browser-based acquisition under bounded crawl rules.
4. **Automated evidence classification** — apply deterministic detectors to immutable acquired documents.
5. **Human review and adjudication** — keep uncertain, conflicting or review-dependent evidence separate from automated output.
6. **Regulatory mapping** — map generic reviewed evidence to versioned regulatory profiles such as LGPD.
7. **Derived metrics and interpretation** — aggregate only after the earlier states and denominators are preserved.

### Population and units of analysis

The starting population is the complete set of source records obtained from the IPB directory, rather than only the websites that could eventually be crawled.

The protocol distinguishes several units:

- **source record** — the original value obtained from the source population;
- **normalized resource** — a protocol-derived HTTP/HTTPS representation when possible;
- **unique website measurement unit** — the canonical website after duplicate references are resolved;
- **fetched document** — an immutable acquisition artifact;
- **evidence item** — a detector or reviewer observation tied to an exact artifact;
- **ResearchRun** — the versioned execution context binding dataset, code, protocol, profiles and configuration.

This distinction prevents sample construction from becoming an undocumented preprocessing step.

### Acquisition protocol

Website acquisition is bounded and reproducible. The run records the crawl configuration, HTTP/browser behavior, retries, acquisition failures and stop conditions.

Failures such as DNS errors, TLS failures, HTTP errors, rate limiting, anti-bot challenges, timeouts and crawl-budget exhaustion are treated as **research observations**, not as reasons to silently remove a website from the denominator.

Missing-data states are also explicit. `unknown`, `unavailable`, `invalid`, `excluded` and `not-applicable` are not automatically treated as `absent`.

### Evidence classification and ML

Deterministic rules remain the transparent baseline for observable privacy signals.

The experimental PHP-native ML path is used as a **second opinion**, not as ground truth. A model prediction preserves its probability, threshold, artifact hash, dataset provenance and feature-pipeline version. When ML disagrees with a deterministic rule, the case is routed to the auditable review workflow rather than silently overriding the rule.

### Reproducibility and validity

Each ResearchRun records the exact protocol version, schema version, detector versions, regulatory-profile versions, acquisition configuration and runtime components used for the measurement.

This is especially important for the comparison with the 2025 monograph: a website is a changing research object. The current run should therefore be interpreted as a new observation under a newer and more explicit protocol, not as a direct remeasurement under identical conditions.

The full protocol is versioned in the repository:

https://github.com/vitormattos/privacy-evidence/blob/main/docs/research/protocol.md

### Methodological flow

The source for the diagram below is kept as Mermaid in the repository so the research flow remains version-controlled even though DEV does not render Mermaid natively.

```text
source population
      |
      v
source preservation
      |
      v
normalization + resource classification
      |
      v
eligibility + deduplication
      |
      v
bounded HTTP/browser acquisition
      |
      v
immutable documents
      |
      +----------------------+
      |                      |
      v                      v
deterministic evidence    PHP-native ML
      |                      |
      +----------+-----------+
                 |
                 v
          human review
                 |
                 v
       regulatory mapping
                 |
                 v
     metrics + interpretation
```

## Results from the IPB Analysis

The empirical study did **not** start with 566 websites. It started with the complete source population available from the public IPB directory: **2,993 records representing congregations and related entries across Brazil**.

That full population is part of the result.

The research pipeline had to determine what each source record actually represented before any privacy analysis could begin. Some entries pointed to independent institutional websites, while others pointed to social networks, third-party hosted pages, video platforms, link aggregators, malformed addresses or no usable website source at all.

### Connection to the original 2025 monograph

This experiment revisits an analysis first carried out for my 2025 Theology monograph, *A comunhão dos santos frente aos dilemas da ética digital: uma abordagem bíblica sobre a gestão de dados sensíveis na igreja*.

The original discussion appears in **section 3.2.6, “Panorama da conformidade digital na IPB,” on printed page 42** of the monograph (**PDF page 56**). The detailed table is in **Appendix E, “Adequação à LGPD em sites de igrejas da IPB,” on printed page 67** (**PDF page 81**).

{% cta https://vitormattos.github.io/monografia-teologia/monografia.pdf %}
Read the original monograph (PDF)
{% endcta %}

The 2025 study reported:

| 2025 monograph indicator | Result |
| --- | ---: |
| IPB churches considered | **2,935** |
| Churches with a website address in the directory | **640** |
| Addresses that were not social networks | **532** |
| Valid and active websites | **219** |
| Sites with any LGPD mention | **22** |
| Sites with a cookie notice | **16** |
| Sites with a published privacy policy | **15** |
| Sites with a generic DPO/privacy contact | **12** |
| Sites with a specific data-subject-rights form | **5** |
| Sites with an identified DPO | **2** |

The monograph records that the original processing was completed on **June 20, 2025**. The current Privacy Evidence run was performed in **October 2026**, more than a year later.

{% card %}
### This is not a like-for-like percentage comparison

The web is a moving research object. Between June 2025 and October 2026, church websites may have appeared, disappeared, changed domain, changed content or changed privacy disclosures. The IPB source directory itself may also have changed.

In addition, the current Privacy Evidence pipeline has a more explicit population-accounting protocol than the original manual study. It distinguishes source classification, website eligibility, deduplication, acquisition outcome and evidence observability as separate stages.

For that reason, differences between the 2025 and 2026 numbers should not automatically be interpreted as improvement or deterioration in LGPD practices. They reflect a combination of **time**, **changes in the source population**, **changes on the websites themselves**, and **a more rigorous measurement protocol**.
{% endcard %}

The historical value of the comparison is therefore methodological as much as substantive: the 2025 monograph provides the baseline that motivated the project, while the 2026 run demonstrates how the same research problem can be revisited with a reproducible and auditable pipeline.

### From the full IPB population to measurable websites

The complete accounting of the **2,993 source records** was:

| Source classification | Records |
| --- | ---: |
| Institutional website | **529** |
| Third-party hosted page | **60** |
| Social network | **61** |
| Video platform | **6** |
| Link aggregator | **1** |
| Malformed source | **20** |
| Unknown / no usable website source | **2,316** |
| **Total source population** | **2,993** |

{% card %}
### Why this population accounting matters

The **2,404 records that were not eligible for website measurement were not silently removed from the study**.

They remain part of the research population with an explicit classification explaining why the website-measurement stage did not apply. This is important for reproducibility because another researcher can reconstruct how the original IPB directory became the final set of measurable websites.
{% endcard %}

After source classification, **589 records were eligible for website measurement**. Because some source records referred to the same website, deduplication produced **566 unique website measurement units**.

The study therefore followed this population flow:

```text
2,993 IPB source records
        |
        v
source classification
        |
        +--> 2,404 not eligible for website measurement
        |
        v
589 eligible source records
        |
        v
deduplication
        |
        v
566 unique websites
        |
        v
measurement and evidence analysis
```

This distinction is methodologically important. The source population is not equivalent to a ready-made website sample. Building the measurable population is itself an empirical step of the study.

### Measurement outcomes

Among the **566 unique websites**:

| Measurement outcome | Websites | Share |
| --- | ---: | ---: |
| Fully measured | **193** | **34.1%** |
| Partially measured | **12** | **2.1%** |
| Not measurable under the protocol | **361** | **63.8%** |
| **Total** | **566** | **100%** |

The high proportion of non-measurable websites is itself an important empirical result. It shows that large-scale public-web research is constrained not only by the analytical method, but also by the condition of the web population being studied.

The most frequent obstacles included DNS failures, anti-bot mechanisms, HTTP 429 rate limiting, unavailable or redirected websites, TLS failures, timeouts and crawl-budget limits. Instead of excluding these cases after collection begins, Privacy Evidence records them as explicit outcomes.

That changes the interpretation of the study. The result is not merely the subset of websites from which evidence could be extracted. It is a complete trace from the original IPB population to the final state of every source record.

### Observable LGPD-related evidence

For the websites that could be evaluated, the aggregate observability metrics were:

| Metric | Result |
| --- | ---: |
| Average LGPD-oriented evidence coverage | **27.2%** |
| Average full observed support | **5.3%** |
| Average any observed support | **6.6%** |

These values suggest that the privacy-related information evaluated by the protocol is often not clearly observable on public-facing church websites. Evidence related to controller identification, processing purpose, data-sharing information, data-subject rights and retention/duration was frequently absent or indeterminate in the collected public content.

{% card %}
### How to read these percentages

These values are **not compliance rates**.

Privacy Evidence does not evaluate an organization's complete privacy governance program and does not establish legal compliance or non-compliance. It reports whether predefined evidence could be observed on the public website under the documented acquisition and classification protocol.
{% endcard %}

The distinction matters because absence of observable website evidence can have different causes: the information may genuinely be missing, may exist outside the crawled pages, may be provided through another channel, or the site may not have been measurable enough to support a conclusion.

### What the results say about the research method

> **The most relevant outcome was not a single percentage.** It was the ability to reproduce the complete path from all 2,993 IPB source records to the final analytical state.

For every source record, the system can explain:

1. what kind of source it represented;
2. whether website measurement was applicable;
3. whether it referred to a unique website or duplicated another source;
4. whether acquisition succeeded;
5. why measurement was complete, partial or unavailable;
6. what observable privacy evidence was detected;
7. which rule or model produced a classification;
8. which cases still require human review.

That provenance is what the original manual study was missing.

It also exposes an important methodological limitation: **website observability and legal or organizational privacy maturity are different constructs**. A future academic study should therefore treat these results as measurements of public privacy evidence availability, not as a compliance score.

### Implications for future research

The full-population run provides a baseline for several follow-up studies:

1. **Longitudinal analysis** — how public privacy evidence changes over time.
2. **Population comparison** — differences between denominations, organizations or sectors.
3. **Classifier validation** — deterministic and ML-based classifiers against independently human-reviewed samples.
4. **Website characteristics** — whether particular technical or organizational characteristics are associated with greater privacy-information visibility.
5. **Regulatory-profile replication** — reevaluating the same collected evidence against other regulatory profiles without repeating acquisition.

In that sense, the main contribution of the run is not only the descriptive result for the websites that could be measured. It is the preservation of the **entire research population**, including the records for which website analysis was not applicable or technically possible, together with an auditable explanation of how every record reached its final state.

## Code

{% embed https://github.com/vitormattos/privacy-evidence %}

{% cta https://github.com/vitormattos/privacy-evidence %}
Explore Privacy Evidence on GitHub
{% endcta %}

The frozen Weekend Challenge code boundary is commit:

```text
c80503f3adcbe2422ca583d9de51bbe5bb4b35a6
```

The repository was created on October 2, 2026 during the challenge window. The frozen commit was created on October 3, also during the window. Later development is explicitly documented as outside the frozen challenge code boundary unless that boundary is intentionally updated before submission.

Challenge documentation:

https://github.com/vitormattos/privacy-evidence/tree/c80503f3adcbe2422ca583d9de51bbe5bb4b35a6/examples/hacktoberfest

## How I Built It

Privacy Evidence is written in PHP 8.4.

The deterministic research pipeline and the ML experiment are intentionally separate, as described in the methodology above.

For the open-source ML path I used [Rubix ML](https://github.com/RubixML/ML), running locally in PHP.

The model path includes:

- deterministic, leakage-safe dataset preparation;
- a fitted text normalization/vectorization/TF-IDF pipeline;
- one probabilistic classifier per observable evidence signal;
- saved model artifacts with exact SHA-256 and dataset/split/model provenance;
- local training and inference commands;
- a comparison harness for deterministic rules, an internal dependency-free Naive Bayes baseline and Rubix;
- deterministic handling of rule/ML agreement, disagreement and uncertainty;
- strict separation between an AI suggestion and actual human ground truth.

The challenge demo focuses on one concrete failure mode: privacy language can express the same concept without using the exact vocabulary expected by a transparent keyword rule.

The ML model is allowed to say, in effect, "this looks like controller identity evidence." It is **not** allowed to turn that probability into a legal conclusion or impersonate a human reviewer.

That boundary matters to the research design as much as the model itself.

## Why Does Open Innovation Matter?

For this project, open innovation is about reproducibility and control.

The entire challenge demo runs locally. It does not require an OpenAI API key, an Ollama server, Python, a remote inference endpoint or a third-party service holding the research text.

That matters because a scientific workflow should be inspectable enough that another researcher can understand not only the final classification, but also:

- which code version produced it;
- which dataset and split trained the model;
- which feature-pipeline version transformed the text;
- which exact model artifact was used;
- which probability and threshold produced the candidate result;
- how disagreement reached human review.

A closed API can be useful, but it introduces a research dependency whose implementation and model can change outside the repository. For the specific problem I was trying to fix — a previous study that was not reproducible enough — moving the crucial classification step behind an opaque remote API would solve the wrong problem.

Open-source local ML gives me a model I can version, inspect, replace, benchmark and rerun as part of the same research instrument.

There is also an important result I am **not** claiming: the ML model has not been promoted to the project's default detector.

External development data is useful for engineering and feasibility, but the project requires evaluation against independently human-reviewed project evidence before a model can be promoted. Until that exists, deterministic detectors remain the default and ML remains an experimental second opinion.

That limitation is part of the result, not something I want to hide.
