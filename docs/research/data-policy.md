# Research-data privacy, minimization and retention policy

Policy version: **0.1.0-draft**

## Basis

Privacy Evidence processes public web material for research/measurement purposes. Public availability does not make personal data irrelevant to data-protection obligations.

The policy follows data-minimization and good-faith research principles and considers ANPD guidance on academic/study/research processing:
https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia-orientativo-tratamento-de-dados-pessoais-para-fins-academicos-e-para-a-realizacao-de-estudos-e-pesquisas

## Data classes

### Source metadata
IDs, organization/resource names, declared URLs and sampling metadata.

### Raw acquired artifacts
HTML, rendered DOM, screenshots, response headers, network observations, cookies/storage where required.

### Derived evidence
Minimized excerpts, detector outputs, classifications and hashes.

### Review data
Reviewer identifiers/types, labels and rationales.

## Minimization

- collect only artifacts required by a research question/protocol;
- do not collect authentication-only/private content;
- avoid screenshots unless visual state is evidence;
- store minimized excerpts rather than duplicating entire documents in derived exports;
- do not publish unrelated names/e-mails/telephone numbers simply because they appeared in a page.

## Storage/publication

`data/raw/` and `data/restricted/` are not public-source artifacts and are ignored by Git.

Public replication packages prefer:
- hashes;
- URLs/timestamps;
- minimized evidence excerpts where lawful/appropriate;
- derived metrics;
- schemas/protocol/configuration.

## Retention

Until a formal institutional retention schedule exists:
- raw live-web artifacts are retained only as long as necessary for validation/reproducibility of the associated research release;
- release documentation records the retention basis/window actually used;
- derived non-identifying metrics may be retained indefinitely;
- deletion of raw artifacts does not erase their hashes/provenance entries.

A future release must replace this draft with a concrete retention interval justified by the study/release context.

## Access

Restricted artifacts are accessible only to the research operators/reviewers who need them.

## Publication review

Before publishing an export, verify that it does not include:
- unrelated personal data;
- cookies/session identifiers;
- browser storage;
- credentials/tokens;
- raw restricted artifacts.
