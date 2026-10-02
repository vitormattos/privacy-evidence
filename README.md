# Privacy Evidence

**Privacy Evidence** is open-source research software for reproducible, large-scale collection and analysis of publicly observable privacy evidence across websites.

It is intended for researchers, privacy teams, auditors, consultants and organizations that need to inspect hundreds or thousands of public web resources consistently instead of relying on manual, one-off reviews.

## Why Privacy Evidence

Manual privacy reviews do not scale well, are difficult to reproduce and often mix technical observations with legal conclusions. Privacy Evidence is designed to preserve the chain from what was actually observed on a website to the evidence classification, human review and regulatory interpretation applied later.

## What it does

Privacy Evidence is being designed to:

- ingest generic website datasets and pluggable source adapters;
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

Privacy Evidence is in its initial architecture and research-protocol phase.

The project originates from a 2025 academic case study of websites declared by churches of the Igreja Presbiteriana do Brasil. That historical implementation remains preserved in the separate `webscraping-anuario-igrejas-ipb` repository and is treated as a legacy research artifact, not as this project's codebase.

## High-level pipeline

```text
dataset/source
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

- Research protocol: `docs/research/`
- Architecture: `docs/architecture/`
- Regulatory profiles: `docs/regulatory/`
- Development: `docs/development/`
- Architecture decisions: `docs/adr/`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Changes that affect measurement semantics, detectors, regulatory mappings or research outputs require additional methodological review.

## Security

See [SECURITY.md](SECURITY.md). The crawler processes untrusted URLs, HTML and potentially JavaScript, so network and browser safety are first-class requirements.

## Citation

Citation metadata is available in [CITATION.cff](CITATION.cff).

## License

Privacy Evidence is free software licensed under the GNU Affero General Public License v3.0 or later (**AGPL-3.0-or-later**). See [LICENSE](LICENSE).
