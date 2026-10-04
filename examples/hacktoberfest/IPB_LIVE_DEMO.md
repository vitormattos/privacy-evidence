<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Live IPB validation demo

This demo starts from the same kind of source that motivated the original research: the official IPB church directory exposed through the IPB/iCalvinus integration.

The official IPB website links its **Igrejas** section to the iCalvinus church directory. Privacy Evidence already has an IPB source adapter for that directory, so the demo now begins from the complete current source rather than from a hand-picked list of church websites.

It is intended for **capability validation and challenge demonstration**, not for estimating how common any privacy practice is across the IPB.

## Reproducible source path

The demo executes this pipeline:

```text
official IPB/iCalvinus directory
        ↓
preserved HTML snapshot + SHA-256
        ↓
IPB source adapter
        ↓
URL normalization and resource classification
        ↓
institutional websites only
        ↓
deterministic seeded sample
        ↓
live acquisition / evidence detectors
        ↓
review queue
        ↓
LGPD / GDPR / cookie profiles
        ↓
report / replication package
```

This intentionally reproduces a key part of the original research problem: the source directory can contain entries that are not directly usable institutional websites. Privacy Evidence preserves the original source value and classifies social networks, video platforms, link aggregators, hosted pages, malformed values and institutional websites instead of silently cleaning the list by hand.

## Run

After `composer install`:

```bash
examples/hacktoberfest/run-ipb-live-demo.sh
```

The script:

1. downloads and preserves the current official IPB/iCalvinus church-directory snapshot;
2. runs `source:import` over the complete snapshot so the source hash and classifications are visible;
3. deterministically selects 8 entries classified as `institutional_website` using seed `hacktoberfest-ipb-v1`;
4. records a sample manifest containing the original source snapshot SHA-256, classifier type, seed, eligible count and selected count;
5. runs acquisition and evidence detection on that generated sample;
6. shows durable run status;
7. applies all versioned regulatory profiles;
8. generates the export/report package.

The 8 sites are therefore **not chosen manually**. Given the same preserved directory snapshot, classifier version, seed and limit, the same website sample is selected.

If the optional Playwright browser dependencies are installed, browser escalation is available for pages that need it. Without Playwright, the demo still exercises the HTTP acquisition path.

## Why snapshot first

The live IPB directory can change. A future run may contain different churches or website values.

The demo therefore saves the exact directory response before parsing it. The snapshot SHA-256 becomes part of the sample provenance. This makes it possible to distinguish:

- **reproduction**: rerun deterministic parsing/classification/sampling from the same preserved snapshot;
- **replication**: download the current directory again and measure the websites as they exist at a later date.

## What to show in a video

A strong challenge recording can show:

1. the official IPB **Igrejas** page as the research source;
2. `source:fetch-ipb` saving the exact source snapshot and SHA-256;
3. `source:import` classifying the complete directory instead of manually deleting unsuitable entries;
4. `source:sample-websites` generating the 8-site sample and manifest from a fixed seed;
5. the resulting CSV, proving that the input to the crawl is derived rather than hand-picked;
6. the live `run` producing a ResearchRun id;
7. `status` showing jobs, observations and pending human reviews;
8. `analyze` applying the same generic evidence to LGPD/GDPR/cookie profiles;
9. `report` producing the replication/export package;
10. the separate model-required demo in `run-demo.sh`, showing the PHP-native ML second opinion and auditable rule/ML disagreement.

This demonstrates the exact progression that was difficult to reproduce in the original study: source population → classification → sample → crawl → evidence → analysis.

## Interpretation boundary

The demo reports **observable evidence and profile support states**. It does not certify that a church or organization is legally compliant or non-compliant.

For example, finding a privacy notice, DPO contact or cookie control is evidence about the measured public resource. Missing evidence on that resource is not proof that the organization fails a legal obligation.

The generated sample is a demonstration sample, not a statistically representative estimate of the IPB population.

## Reproducibility

Pure parsing, URL normalization, classification and seeded selection are deterministic for the same preserved snapshot and versions.

Live website acquisition is externally variable. Websites can disappear, redirect or change content. Each acquired artifact is therefore hashed and tied to the ResearchRun so later replications can explain differences rather than pretending the live web is immutable.
