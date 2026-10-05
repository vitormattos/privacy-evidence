---
title: I turned my old privacy study into a reproducible research tool for my master's advisor
published: false
tags: devchallenge, weekendchallenge, hf26challenge
---

*This is a submission for the [Hacktoberfest Weekend Challenge: Build for a Friend](https://dev.to/challenges/hacktoberfest-weekend-2026-10-01).*

## What I Built

I dedicate **Privacy Evidence** to **Igor Scaliante Wiese, my master's advisor at UTFPR and professor of the Free Software Development course**.

A previous academic monograph I wrote at Seminário Simonton explored digital ethics and the effect of digital practices on Christian fellowship. One part of the research looked at privacy and data-protection practices on websites associated with churches of the Igreja Presbiteriana do Brasil (IPB).

The hard part was not only interpreting LGPD-related evidence. It was building the sample itself.

I started from the public IPB directory and worked through entries one by one. Some links were Facebook or Instagram pages. Some were link aggregators. Some domains existed but no longer served a site. Some returned errors. After manually reducing the list to actual websites, I still had to inspect the remaining sites individually.

That process helped answer the research question, but it had a serious empirical-software-engineering problem: too much of the workflow was manual. Another researcher could read the methodology, but could not reliably replay the same sequence of filtering, collection, classification and review from the original source data.

Privacy Evidence is the tool I wanted that study to have, but the current goal is broader: turn the old work into something my advisor can inspect as empirical software-engineering research and that can support the scientific publications I need to produce during the master's program.

Igor did not ask me to build this exact software, and I am not claiming that he has already validated its results. I am dedicating the work to him because his role as my advisor and as a professor of Free Software Development makes him a real academic reference for the kind of research artifact I want this project to become: open, inspectable, reproducible and suitable for scientific scrutiny.

It accepts a population of websites from a source adapter, preserves provenance, acquires public evidence through a repeatable protocol, classifies observable privacy signals, sends ambiguous cases to review, and lets the same generic evidence be evaluated against versioned profiles such as LGPD, GDPR and cookie/ePrivacy requirements.

It deliberately does **not** say that a website or organization is legally compliant. A public website is only one observable slice of a broader privacy program.

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

**Public demo:** https://github.com/vitormattos/privacy-evidence/actions/runs/37265354267

This GitHub Actions run executes the real PHP-native Rubix model build and inference path in public. The successful output shows `aiAtCore: true`, `modelRequired: true`, a `controller_identity` ML candidate with probability `1`, rule/ML disagreement, one pending review case and an auditable `ai_suggestion`.

## Results from the IPB Analysis

The full-population run made it possible to move beyond a proof of concept and observe how the research protocol behaves on a real, heterogeneous population.

The source contained **2,993 records** from the public IPB directory. After classification and normalization, **589 records were considered eligible for website measurement**, corresponding to **566 unique website measurement units** after duplicate references were resolved.

This distinction matters methodologically. The original source is not equivalent to a clean list of independent websites: it contains duplicated references, social-network pages, malformed entries, third-party pages and addresses that no longer resolve. Treating those cases explicitly prevents the analysis from silently shrinking the population and makes the selection process reproducible.

### Measurement outcomes

Among the **566 unique websites**:

- **193 (34.1%)** were fully measured;
- **12 (2.1%)** were partially measured;
- **361 (63.8%)** could not be measured under the defined protocol.

The high proportion of non-measurable websites is itself an important empirical result. It shows that large-scale public-web research is constrained not only by the analytical method, but also by the condition of the web population being studied.

The most frequent obstacles included DNS failures, anti-bot mechanisms, HTTP 429 rate limiting, unavailable or redirected websites, TLS failures, timeouts and crawl-budget limits. Instead of excluding these cases after collection begins, Privacy Evidence records them as explicit terminal outcomes.

That decision changes the interpretation of the study: the result is not simply a set of websites for which evidence was found, but a complete accounting of what happened to every source record and why some observations could not be produced.

### Observable LGPD-related evidence

For the websites that could be evaluated, the average LGPD-oriented evidence coverage across the unique website population was approximately **27.2%**.

The average rate of **full observed support** across the evaluated requirements was approximately **5.3%**, while **any observed support** was approximately **6.6%**.

These low values suggest that the privacy-related information evaluated by the protocol is often not clearly observable on public-facing church websites. In particular, evidence related to controller identification, processing purpose, data-sharing information, data-subject rights and retention/duration was frequently absent or indeterminate in the collected public content.

This finding should be interpreted carefully. Privacy Evidence does not evaluate an organization's complete privacy governance program and does not establish legal compliance or non-compliance. It only reports whether predefined evidence could be observed on the public website under the documented acquisition and classification protocol.

The distinction is important because absence of observable website evidence can mean several different things: the information may genuinely be missing, it may exist outside the crawled pages, it may be provided through another channel, or the site may not have been measurable enough to support a conclusion.

### What the results say about the research method

The most relevant outcome for me was not a single percentage. It was the ability to reproduce the entire path from the original directory to the final analytical state.

For every source record, the system can explain whether it represented an independent website, whether it was eligible for measurement, whether acquisition succeeded, what evidence was observed, which detector or model produced the classification, and why a case remained unavailable, indeterminate or pending human review.

That provenance is what the original manual study was missing.

The results also expose an important methodological limitation for future work: website observability and legal or organizational privacy maturity are different constructs. A future academic study should therefore treat these results as measurements of **public privacy evidence availability**, not as a compliance score.

### Implications for future research

The full-population run provides a baseline for several follow-up studies:

- longitudinal analysis of how privacy evidence changes over time;
- comparison between denominations, organizations or sectors;
- validation of deterministic and ML-based classifiers against independently human-reviewed samples;
- investigation of whether specific website characteristics are associated with greater privacy-information visibility;
- replication of the protocol against other regulatory profiles without repeating the entire acquisition step.

In that sense, the main contribution of the run is not only the descriptive result for the IPB population. It is the creation of a reproducible research instrument that can support new empirical questions while preserving the limits of what the collected evidence can actually demonstrate.


## Code

Repository:

https://github.com/vitormattos/privacy-evidence

The frozen Weekend Challenge code boundary is commit:

```text
c80503f3adcbe2422ca583d9de51bbe5bb4b35a6
```

The repository was created on October 2, 2026 during the challenge window. The frozen commit was created on October 3, also during the window. Later development is explicitly documented as outside the frozen challenge code boundary unless that boundary is intentionally updated before submission.

Challenge documentation:

https://github.com/vitormattos/privacy-evidence/tree/c80503f3adcbe2422ca583d9de51bbe5bb4b35a6/examples/hacktoberfest

## How I Built It

Privacy Evidence is written in PHP 8.4.

The deterministic research pipeline and the ML experiment are intentionally separate:

```text
source population
      |
      v
deterministic acquisition
      |
      v
immutable observable evidence
      |
      +-------------------+
      |                   |
      v                   v
transparent rules    PHP-native ML
      |                   |
      +--------+----------+
               |
               v
        auditable review
               |
               v
     regulatory profiles
               |
               v
 reproducible analysis
```

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

## My Agent Session

Optional for this entry. No DevRelay session is currently included.

## Prize Categories

No partner prize category is claimed for this submission.

