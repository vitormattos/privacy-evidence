# Privacy Evidence Research Protocol

Protocol version: **0.2.0-draft**

## Research object

Privacy Evidence studies **publicly observable privacy evidence exposed by digital resources**. The object is not organization-wide legal compliance.

The research program's central phenomenon is **measurement attrition and evidence provenance in empirical web research**: how an intended web population becomes an effectively measurable analytical population, which observations are lost or transformed, and whether those losses affect reproducibility or interpretation.

The canonical contribution statement, propositions and claim boundaries are defined in `docs/research/research-contribution.md`.

## Goal (GQM)

Analyze public digital resources for the purpose of characterizing observable privacy evidence with respect to transparency, rights channels, privacy contacts, cookie/consent behavior and related signals, from the viewpoint of researchers and privacy practitioners, in the context of reproducible large-scale web measurement.

## Research questions

### RQ1 — Resource availability
What proportion of declared digital resources are technically observable under the acquisition protocol?

### RQ2 — Resource type
What types of public digital resources are declared or discovered?

### RQ3 — Privacy evidence
Which defined privacy-evidence signals are publicly observable?

### RQ4 — Evidence intensity and uncertainty
How complete, specific and review-dependent are the observed signals?

### RQ5 — Regulatory-profile mapping
How do reviewed generic evidence items map to versioned LGPD, GDPR and cookie/ePrivacy profiles under explicit applicability rules?

### RQ6 — Detector performance
How accurately do automated detectors reproduce reviewed labels on the designated evaluation dataset?

### RQ7 — Acquisition cost
What HTTP/browser/resource cost is required to produce the observations under bounded crawl budgets?

## Units

- **Entity:** organization or sampled subject represented by source metadata.
- **Declared resource:** raw source value that purports to identify a digital resource.
- **Normalized resource:** protocol-derived HTTP/HTTPS representation where possible.
- **Fetched document:** immutable acquisition artifact for one response/rendered document.
- **Evidence item:** detector/reviewer output tied to an exact artifact.
- **ResearchRun:** versioned execution context that binds dataset, code, protocol, profiles and configuration.

## Measurement layers

1. raw/source observation;
2. normalization/classification;
3. technical acquisition observation;
4. automated evidence classification;
5. human review/adjudication;
6. regulatory mapping;
7. derived metric/research interpretation.

No layer may silently overwrite an earlier one.

## Missing-data states

At minimum: present, absent, unknown, unavailable, invalid, excluded and not-applicable. Unavailable/unknown are never silently counted as negative.

## Claims permitted

The protocol may support claims about evidence observable under the specified acquisition and review procedure and, where the required analyses have been executed, about measurement attrition, reproducibility, detector validity and transferability as defined in `docs/research/research-contribution.md`.

## Claims not permitted

Website observations alone do not establish:
- organization-wide legal compliance or non-compliance;
- internal processing inventories;
- actual legal-basis validity;
- internal retention practice;
- organizational security controls;
- whether a data-subject request would be fulfilled correctly.

## Methodological basis

The project uses Goal–Question–Metric operationalization and follows empirical-software-engineering practices. GQM is the operationalization mechanism, not the entire research design. The broader methodological stack is being formalized under the research-model work and includes Engineering Research / Design Science, observational empirical evaluation, independent human-reference validation and reproduction/replication where applicable.

The ACM SIGSOFT Empirical Standards are used as a reporting/design checklist, especially the Engineering Research and Sampling standards.

References:
- Basili, Caldiera & Rombach, Goal Question Metric paradigm.
- Ralph et al., *Empirical Standards for Software Engineering Research*, DOI 10.48550/ARXIV.2010.03525.
- ACM SIGSOFT Empirical Standards: https://github.com/acmsigsoft/EmpiricalStandards

## Protocol evolution

Measurement-semantic changes require version updates according to `docs/research/versioning.md`. ResearchRun manifests record the exact protocol version.
