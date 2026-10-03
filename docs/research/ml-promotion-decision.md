# PHP-native ML promotion decision

Decision date: **2026-10-03**

Decision: **do not promote the PHP-native ML path outside the experimental boundary yet**.

## What is complete

The experiment now has a reusable project-owned architecture, pinned Rubix/Tensor dependencies, a dependency-free Naive Bayes baseline, versioned external-dataset preparation and label mapping, leakage-safe splits, a persisted TF-IDF feature pipeline, per-signal probabilistic Rubix models, reproducible train/inference commands, a common comparison harness, auditable ML review suggestions, and ML-specific security/reproducibility bounds.

The comparison harness evaluates deterministic rules, the internal Naive Bayes baseline and Rubix on the same held-out external development cases with confusion matrices, precision, recall, F1, support, coverage and traceable FP/FN records.

## Why there is no promotion

The project protocol requires evaluation on independently reviewed project evidence before an ML model can become a default detector or redefine evidence state. That final project gold dataset is not available yet because #32 and #33 still require genuine independent human annotations.

External Claudinha-derived development data is useful for engineering and feasibility work, but it is not a substitute for the project's target population. Its LGPD-oriented taxonomy and Portuguese privacy-policy distribution can differ materially from the generic multi-regulation evidence population Privacy Evidence is designed to measure.

The current Rubix dependency is also pinned to a release-candidate version. That is acceptable inside the experimental boundary, but it is another reason not to make the path a default research instrument without project-population validation.

## Consequence

- deterministic evidence detectors remain the default research instrument;
- PHP-native ML remains an explicit experimental second opinion;
- ML output can prioritize review and expose disagreement, but cannot overwrite detector output or human decisions;
- no legal-compliance conclusion is produced from ML output;
- the project should revisit this decision after #32/#33 provide independently reviewed ground truth and the canonical evaluator can run on that held-out population.

This is a **no-promotion / insufficient-project-evidence** decision, not a negative claim about Rubix or the eventual value of ML.
