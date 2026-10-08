# Privacy Evidence

**Privacy Evidence** is open-source research software for reproducible, large-scale collection and analysis of publicly observable privacy evidence across websites.

It is intended for researchers, privacy teams, auditors, consultants and organizations that need to inspect hundreds or thousands of public web resources consistently instead of relying on manual, one-off reviews.

## Why Privacy Evidence

Manual privacy reviews do not scale well, are difficult to reproduce and often mix technical observations with legal conclusions. Privacy Evidence is designed to preserve the chain from what was actually observed on a website to the evidence classification, human review and regulatory interpretation applied later.

## What it does

Privacy Evidence can:

- ingest canonical website datasets produced by any external extraction workflow;
- acquire public web content using HTTP first and browser automation only when necessary;
- detect and preserve traceable privacy evidence;
- support human review and auditable adjudication;
- evaluate the same evidence against versioned regulatory profiles such as LGPD, GDPR and cookie/ePrivacy requirements;
- process large datasets with bounded concurrency, resumable jobs and explicit resource budgets;
- generate reproducible research outputs with provenance, protocol versions and machine-readable exports.

## What it does not do

Privacy Evidence does **not** certify that an organization is legally compliant or non-compliant.

A public website exposes only part of an organization's data-protection practices. The project therefore separates:

1. technical observations;
2. evidence classification;
3. regulatory mapping;
4. human adjudication;
5. research interpretation.

This separation is a core methodological requirement.

## Use cases

- privacy and transparency diagnostics across large website populations;
- empirical research on public data-protection practices;
- longitudinal monitoring of observable privacy signals;
- comparison of privacy evidence across sectors or populations;
- evaluation of automated privacy detectors;
- reproducible LGPD/GDPR/ePrivacy-oriented web measurement.

## Project status

Privacy Evidence is an active empirical-research software project. The core pipeline for source import, bounded HTTP/browser acquisition, evidence detection, regulatory mapping, provenance, resumable execution, reporting and human-review preparation is implemented and has been exercised on real-world populations.

The current research program focuses on **measurement attrition and evidence provenance in empirical web research**: how a declared web population becomes an effectively measurable analytical population, what is lost along that path, and how provenance affects reproducibility and interpretation.

Frozen empirical results now exist for **RQ1 measurement attrition** and **RQ2 missingness sensitivity** on the IPB case, including reconciled population accounting and a predeclared selective-measurability analysis. The scientific manuscript has been populated with those completed results. The main unresolved empirical gate is **independent human validation** of the evidence detectors: the reviewer workflow and pilot protocol are implemented, but publication-grade RQ4 claims remain blocked until genuine independent humans complete the frozen procedure. The controlled WEC baseline (#191), clean-checkout/independent reproduction (RQ3) and publication package remain active work.

The project originates from a 2025 academic case study of websites declared by churches of the Igreja Presbiteriana do Brasil (IPB). That historical implementation remains preserved in the separate `webscraping-anuario-igrejas-ipb` repository and is treated as a legacy research artifact, not as this project's codebase. The IPB study is the origin and first empirical context of the research problem, not a claim that the scientific contribution is specific to religious organizations.

For the current execution state and dependency graph, see GitHub issue **#88 — Master execution roadmap and phase gates**.

## Hacktoberfest Weekend Challenge 2026

The challenge code boundary is frozen at commit `c80503f3adcbe2422ca583d9de51bbe5bb4b35a6`, created during the official October 2–5 entry window. Commits after that SHA are outside the frozen entry unless the boundary is explicitly updated before submission.

See `docs/challenge/hacktoberfest-weekend-2026.md` for the exact timestamps, boundary rules and included capabilities. The reproducible local ML demo is in `examples/hacktoberfest/`.

## High-level pipeline

```text
canonical dataset
    ↓
HTTP acquisition
    ↓
adaptive browser escalation
    ↓
document/evidence store
    ↓
privacy evidence detectors
    ↓
human review
    ↓
regulatory profiles
    ↓
analysis and reproducible reports
```

## Documentation

Detailed material lives outside the README:

- Research model and protocol: `docs/research/`
  - scientific contribution: `docs/research/research-contribution.md`
  - RQ/evidence traceability: `docs/research/research-model.md`
  - methodology: `docs/research/methodology.md`
  - empirical-standards checklist: `docs/research/standards-compliance.md`
- Architecture: `docs/architecture/`
- Regulatory profiles: `docs/regulatory/`
- Development: `docs/development/`
- Architecture decisions: `docs/adr/`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes that affect measurement semantics, detectors, regulatory mappings or research outputs require additional methodological review.

## Security

See [SECURITY.md](SECURITY.md). The crawler processes untrusted URLs, HTML and potentially JavaScript, so network and browser safety are first-class requirements.

## Citation

Citation metadata is available in [CITATION.cff](CITATION.cff), with BibTeX, CSL JSON and RIS exports under [`paper/citation/`](paper/citation/) and machine-readable manuscript metadata in [`paper/scholarly-metadata.json`](paper/scholarly-metadata.json).

## License

Privacy Evidence is free software licensed under the GNU Affero General Public License v3.0 or later (**AGPL-3.0-or-later**). See [LICENSE](LICENSE).
