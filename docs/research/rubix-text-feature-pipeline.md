# Rubix text feature pipeline

Status: **experimental**

The PHP-native classifier path uses a project-owned feature-pipeline wrapper around Rubix ML preprocessing. Application/domain code supplies text segments and receives numeric vectors plus project-owned metadata; Rubix transformer objects remain inside `Experiment/Rubix`.

## Version 1.0.0

The baseline pipeline is:

1. `MultibyteTextNormalizer` for lowercase multilingual normalization;
2. `WordCountVectorizer` with a bounded vocabulary;
3. `TfIdfTransformer` fitted on the training corpus.

The default maximum vocabulary is 2,048 terms. Callers may select a smaller bound for fixtures or experiments; the configured maximum and fitted output dimensions are recorded in pipeline metadata.

Training rejects blank texts. After fitting, unknown tokens and empty inference text produce bounded zero vectors rather than changing vocabulary or refitting state. Inference therefore uses exactly the fitted vocabulary and TF-IDF state from training.

## Persistence

The fitted pipeline can be stored and reloaded as an RBX artifact. The artifact contains the fitted vectorizer and TF-IDF state as one versioned unit. Metadata records:

- project pipeline version;
- Rubix ML version;
- maximum vocabulary size;
- fitted feature dimensions;
- normalizer, vectorizer and weighting implementation identities.

RBX provides format/version and integrity checking around Rubix persistence. These experimental artifacts are currently intended for project-generated local/CI data. The broader untrusted-artifact validation and resource limits are part of the ML hardening work in #110.

No external service or model download is required.
