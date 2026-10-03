# PHP-native ML security and reproducibility

Status: **experimental**

The PHP-native ML path is intentionally bounded. These controls protect CI/research runs from accidental or malicious resource exhaustion and make the artifact trust boundary explicit.

## Resource limits

- text segment: at most 65,536 bytes;
- vocabulary: at most 8,192 features (default 2,048);
- training set: at most 10,000 examples per signal model;
- RBX model artifact: at most 16 MiB;
- model metadata sidecar: at most 256 KiB.

Limits are checked before expensive fitting or artifact deserialization where applicable.

## Artifact trust model

RBX artifacts contain serialized PHP object state. Treat model artifacts as **untrusted input unless they were produced by the project and their provenance is known**. Loading a signal model requires:

1. readable, non-empty artifact and sidecar within the configured size bounds;
2. a syntactically valid metadata sidecar;
3. a lowercase SHA-256 in the sidecar;
4. exact SHA-256 match before RBX deserialization;
5. RBX format/integrity validation;
6. expected project model class;
7. exact match between embedded model provenance and sidecar provenance.

Do not bypass these checks for downloaded artifacts.

## Reproducibility

The current initial estimator is Gaussian Naive Bayes. Its training path has no project-controlled random split or seed. External train/validation/development assignment is deterministic and its seed/split hash are stored in the dataset manifest. Training timestamps are passed explicitly to the CLI rather than read implicitly from the clock.

The fitted preprocessing pipeline and estimator are persisted together. Rubix and Tensor are pinned to exact release-candidate intervals in Composer and are represented in the lock file. Release SBOM generation covers Composer dependencies, including the ML stack.

## CI

Default unit/integration tests use tiny local fixtures and do not download datasets or models. No remote inference provider is required.
