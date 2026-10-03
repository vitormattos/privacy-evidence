<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Live IPB validation demo

This demo runs the real Privacy Evidence acquisition, detector, review-queue and regulatory-analysis pipeline against a small curated set of public Igreja Presbiteriana do Brasil websites.

It is intended for **capability validation and challenge demonstration**, not for estimating how common any privacy practice is across the IPB.

## Dataset

`ipb-live-demo-sites.csv` contains eight public websites:

1. the national IPB website;
2. Igreja Presbiteriana de Vila Jardim (RJ);
3. Igreja Presbiteriana de Madureira (RJ);
4. Igreja Presbiteriana Libertas (RJ);
5. Igreja Presbiteriana em Parque Aurora (RJ);
6. Igreja Presbiteriana do Brasil em Serra Negra (SP);
7. Igreja Presbiteriana do Brasil em Alterosa (MG);
8. Igreja Presbiteriana do Brasil em Nova Metrópole (CE).

The sample is deliberately small and curated. It was chosen to exercise the actual research workflow on sites from the population that motivated the project, not to provide a statistically representative sample or a legal-compliance ranking.

The URLs were publicly reachable when the demo dataset was prepared on 2026-10-03. Live-web availability and content are expected to change.

## Run

After `composer install`:

```bash
examples/hacktoberfest/run-ipb-live-demo.sh
```

The script executes the real project commands in sequence:

```text
source:import
    ↓
run
    ↓
status
    ↓
analyze
    ↓
report
```

The output includes the durable ResearchRun id and the generated export directory.

If the optional Playwright browser dependencies are installed, browser escalation is available for pages that need it. Without Playwright, the same demo still exercises the HTTP acquisition path.

## What to show in a video

A useful challenge recording can show:

1. the CSV input;
2. `source:import` preserving the dataset snapshot hash and normalized resources;
3. the live `run` producing a ResearchRun id;
4. `status` showing jobs, observations and pending human reviews;
5. `analyze` applying the same generic evidence to the versioned LGPD/GDPR/cookie profiles;
6. `report` producing the replication/export package;
7. the separate model-required demo in `run-demo.sh`, showing the PHP-native ML second opinion and auditable rule/ML disagreement.

This combination demonstrates both sides of the project: a real live-web research pipeline and the challenge-specific local open-source ML capability.

## Interpretation boundary

The demo reports **observable evidence and profile support states**. It does not certify that a church or organization is legally compliant or non-compliant.

For example, finding a privacy notice, DPO contact or cookie control is evidence about the measured public resource. Missing evidence on that resource is not proof that the organization fails a legal obligation.

## Reproducibility

A live run is a **replication**, not a byte-identical reproduction: websites can change between runs.

Privacy Evidence therefore records the dataset hash, source snapshot, code/protocol/detector/profile versions, acquired artifact hashes and run configuration. Deterministic processing can be reproduced from the same preserved inputs; a new live crawl is a new temporal observation.
