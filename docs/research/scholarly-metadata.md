# Scholarly metadata status

Metadata version: **0.1.0**

This file records what can and cannot currently be exposed as scholarly metadata without inventing publication identifiers.

## Available now

The repository provides:
- canonical software metadata in `CITATION.cff`;
- BibTeX, CSL JSON and RIS software-citation exports under `paper/citation/`;
- machine-readable manuscript/research metadata in `paper/scholarly-metadata.json`;
- exact current manuscript title and author name;
- central phenomenon, contribution statement, paper RQs and methodological stack;
- the frozen IPB ResearchRun and source-dataset hash used by completed RQ1/RQ2 analyses;
- explicit links from RQs to frozen evidence;
- an explicit claim boundary that observable evidence is not legal compliance.

## Intentionally absent

The repository does **not** invent:
- an ORCID;
- an affiliation;
- a paper DOI;
- a software DOI;
- a replication-package DOI;
- a journal/conference name, volume, issue or page range.

ORCID/affiliation should be added only when the author provides the authoritative values. Persistent identifiers depend on the publication/release strategy in #199.

## Public HTML metadata

Issue #198 requires `schema.org/ScholarlyArticle` and `citation_*` metadata for any public HTML paper page. There is currently no canonical public HTML paper page in this repository, so adding those tags now would create an artificial publication surface. When such a page exists, generate metadata from `paper/scholarly-metadata.json` rather than maintaining a second manual source.

## Completion boundary

#198 remains open until:
1. authoritative ORCID/affiliation values are provided, if applicable;
2. the publication/release path creates stable software/data identifiers;
3. any canonical public HTML manuscript surface exposes matching semantic metadata;
4. the final citation files are regenerated from the frozen publication metadata.
