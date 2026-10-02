# Focused literature review: automated privacy evidence analysis

Review version: **0.1.0**

## Scope

This review identifies reusable methods, datasets and architectural practices for Privacy Evidence. It does not treat performance reported on external datasets as evidence that a method will transfer unchanged to Portuguese/LGPD or to the project's target populations.

## OPP-115

The OPP-115 corpus contains 115 website privacy policies with annotated data practices. According to the Usable Privacy Policy Project, each policy was annotated by three graduate law students. The corpus is useful for:
- annotation-schema inspiration;
- privacy-policy category examples;
- benchmarking English-language policy classifiers.

Limitations for this project:
- English-language/domain transfer;
- corpus size;
- annotation licensing constraints;
- policy-text analysis does not cover page discovery, cookie behavior or Portuguese terminology.

Source: https://usableprivacy.org/data

## Polisis

Polisis (USENIX Security 2018) demonstrated scalable automated privacy-policy analysis using deep learning and multi-dimensional policy queries.

Reusable ideas:
- segment policy text before classification;
- represent multiple data-practice dimensions rather than one compliance score;
- expose classifier output as evidence categories.

Limitations:
- model/data assumptions are tied to English privacy-policy corpora;
- reported external performance is not sufficient to adopt a model here;
- modern transformer alternatives may outperform the original architecture but still require project-specific evaluation.

Source: https://www.usenix.org/conference/usenixsecurity18/presentation/harkous

## Web privacy measurement / OpenWPM

Englehardt & Narayanan's 1-million-site measurement showed that full browsers, parallelism, failure recovery and rich browser instrumentation can support very large web-privacy studies.

OpenWPM's current architecture separates:
- task orchestration;
- browser-manager processes;
- structured/unstructured storage;
- logging;
- watchdog/resource monitoring.

Reusable principles:
- browser process isolation;
- bounded browser pools;
- explicit command status/failure taxonomy;
- storage isolated from browser crashes;
- versioned configuration and visit identity;
- watchdog/resource telemetry.

Privacy Evidence should adapt these principles without copying OpenWPM's architecture wholesale.

Sources:
- https://doi.org/10.1145/2976749.2978313
- https://openwpm.readthedocs.io/en/stable/Platform-Architecture.html
- https://openwpm.readthedocs.io/en/stable/Architecture-Internals.html

## Method selection

Initial detector baseline should be transparent and rule-based because it:
- is inspectable;
- works with small project-specific fixtures;
- exposes matched rules;
- is inexpensive at scale.

NLP/ML is a later experiment. Adoption requires measurable improvement on the project's reviewed evaluation set, including Portuguese/English breakdown where sample size permits.

## Research decisions

1. Use external corpora to inform taxonomy and experiments, not as direct ground truth for this project's population.
2. Preserve detector abstention/uncertainty.
3. Evaluate discovery and behavioral evidence separately from natural-language policy classification.
4. Keep browser instrumentation as an acquisition capability, not as the evidence model itself.
5. Report per-signal precision, recall, F1 and support before aggregate scores.
