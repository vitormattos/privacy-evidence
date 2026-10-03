# External training datasets

External corpora are development inputs, not Privacy Evidence human ground truth.
Their annotations must pass through an explicit project mapping before they can
be interpreted as candidate evidence signals.

## Claudinha LGPD Corpus v1

- Dataset: Claudinha Data Protection Law (LGPD) Corpus
- DOI: `10.5281/zenodo.13371639`
- Version: `v1`
- Publisher: Instituto Lawgorithm
- Language: Portuguese
- License: CC BY 4.0
- Source file: `corpus_data_privacy.csv`
- Zenodo-published MD5: `cbc6bc61e1fbe9396b98157355935e41`
- Download: `https://zenodo.org/records/13371639/files/corpus_data_privacy.csv?download=1`

The upstream record describes 6,341 distinct paragraphs and 8,341 annotated
rows. A paragraph can appear more than once because it may have multiple
annotation categories.

The preparation loader verifies the published MD5 before transforming any
record. The generated manifest additionally records SHA-256 for the exact
source bytes and the canonical JSONL output.

### Deliberately excluded fields

The upstream corpus includes legal-compliance annotations. Privacy Evidence
does not import these fields into its experimental training representation:

- `compliance_degree`;
- `non_compliant_clause`.

The canonical development rows retain only source identifiers, text and the
external semantic category. They are not project gold labels.

### Reproducible preparation

Download the exact v1 CSV from the recorded Zenodo URL, then prepare it through the CLI:\n\n```bash\nbin/privacy-evidence experiment:dataset:prepare-claudinha \\\n  /path/to/corpus_data_privacy.csv \\\n  data/derived/experiments/claudinha-v1\n```\n\nThe command performs no network access and prints the generated manifest as JSON only after successful preparation. Internally it delegates to `ClaudinhaCorpusLoader`; parsing and checksum validation are not duplicated in the CLI layer. The loader produces:

- `claudinha-v1.jsonl` — canonical development rows;
- `claudinha-v1.manifest.json` — DOI/license/source checksums, canonical
  checksum, record counts and excluded-field policy.

The generated artifacts belong to development/research data rather than source
code. Do not commit the upstream CSV unless the repository data policy is
explicitly changed to permit vendoring it.

### Methodological boundary

Performance measured on Claudinha is useful for development and feasibility
testing only. It does not satisfy the promotion gate in the PHP-native ML
architecture. Final adoption still requires evaluation against the project's
independently reviewed held-out dataset.
