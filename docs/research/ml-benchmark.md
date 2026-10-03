# PHP-native ML comparison benchmark

The experimental comparison harness evaluates three candidate approaches on the **same held-out external development partition**:

1. current deterministic rule detector;
2. dependency-free multinomial Naive Bayes baseline;
3. Rubix PHP-native probabilistic classifier.

Run:

```bash
bin/privacy-evidence experiment:ml:benchmark \
  data/derived/claudinha/train.jsonl \
  data/derived/claudinha/development.jsonl \
  data/derived/claudinha/claudinha-v1.manifest.json \
  2026-10-03T12:00:00Z \
  data/exports/ml-benchmark.json
```

The report covers the external labels that have both a project mapping and an existing deterministic text detector: controller identity, purpose, recipients, retention, DPO identity/contact, data-subject rights and cookie notice.

For every signal and candidate it reports the confusion matrix, precision, recall, F1, support, coverage/abstention and traceable FP/FN records containing paragraph/source identifiers. It also records exact partition hashes and a model identity hash; the Rubix identity is the saved RBX artifact SHA-256.

No candidate is promoted automatically. Results are explicitly marked `external-development-only` and do **not** satisfy the project adoption gate. Final promotion/no-promotion requires the independently human-reviewed project gold dataset.
