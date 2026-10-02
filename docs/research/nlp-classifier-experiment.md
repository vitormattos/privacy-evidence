# NLP/ML classifier experiment

Status: **experimental; not a default detector**

## Research question

Does a statistical text classifier provide measurable improvement over transparent rule-based Privacy Evidence detectors on the project's independently reviewed dataset?

## Initial baseline

The repository contains a small dependency-free multinomial Naive Bayes implementation under `PrivacyEvidence\\Experiment\\Text`. Its purpose is to provide a reproducible statistical baseline, not to claim state-of-the-art NLP performance.

Rule-based detectors remain the primary baseline.

## Evaluation protocol

Do not select/adopt the statistical classifier based on synthetic fixtures or external benchmark results.

After the reviewed dataset is available:

1. keep development/tuning and final-evaluation partitions separate;
2. train/tune only on the development partition;
3. freeze implementation/configuration before final evaluation;
4. evaluate rules and the statistical baseline on the same final partition;
5. report precision, recall, F1, support and abstention/coverage per target class;
6. report Portuguese and English subsets separately where language metadata is available;
7. inspect FP/FN cases and domain/language transfer failures;
8. record PHP/runtime, classifier version, training dataset hash/partition and tokenization rules;
9. adopt an ML/NLP path only if it shows a meaningful project-dataset benefit that justifies opacity/maintenance cost.

## Future candidates

More sophisticated models (including external pretrained models) may be evaluated later, but must be versioned, reproducible, license-compatible and tested on this project's reviewed Portuguese/English population before adoption.
