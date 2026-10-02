# End-to-end PoC dataset

Dataset version: **0.1.0-poc**

This sample is intentionally small and heterogeneous. It is not a sample for estimating population prevalence and must not be used to make comparative legal-compliance claims about the listed organizations.

## Selection principles

The list includes public sites with a mix of:

- deliberately minimal/static content;
- large institutional sites;
- Portuguese and English content;
- public-sector/regulator content;
- developer/community sites;
- JavaScript-heavy applications;
- sites likely to expose privacy/cookie-related pages or interfaces.

Selection is based on exercising pipeline capabilities, not on an expectation that a site is legally compliant or non-compliant.

## Capability matrix

| ID | Primary intended capability |
| --- | --- |
| poc-01 | minimal static HTML / likely no privacy evidence |
| poc-02 | static standards/institutional crawl |
| poc-03 | multilingual/global public site |
| poc-04 | privacy-related page discovery |
| poc-05 | complex modern site and redirects/security headers |
| poc-06 | privacy/cookie discovery on product site |
| poc-07 | developer-oriented mostly static site |
| poc-08 | large community site |
| poc-09 | Portuguese/Brazilian public-sector content |
| poc-10 | EU institutional/privacy-cookie scenario |
| poc-11 | privacy-oriented service/site |
| poc-12 | JavaScript-heavy modern site / browser escalation candidate |

Controlled fixtures remain authoritative for deterministic failure cases such as unsafe private-network destinations, redirect-to-private-network and intentionally oversized responses.

## Provenance

Only public URLs and project-authored metadata are versioned here. The project does not redistribute fetched third-party HTML in this dataset.

## Interpretation

The dataset exists to prove that the pipeline can ingest, acquire, classify, review and report heterogeneous resources. It does not provide ground-truth compliance labels.
