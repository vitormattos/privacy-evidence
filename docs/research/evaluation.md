# Detector evaluation and gold dataset

## Purpose

The gold dataset estimates detector quality against independently reviewed evidence. It is not a dataset of legal-compliance verdicts.

## Partitions

- **development**: may be inspected while designing rules, thresholds or models;
- **final evaluation**: frozen before final evaluation and never used to tune detector behavior.

Partition membership, selection procedure, random seed (when applicable), dataset hash and annotation-handbook version are recorded.

## Review workflow

1. Acquire and preserve the exact evidence artifact.
2. Build the candidate pool across detector signals, resource types and ambiguity patterns.
3. Select a stratified sample containing positive, negative and uncertain cases.
4. Export the same reviewer-neutral packet before any reviewer imports a decision:
   ```bash
   php bin/privacy-evidence review:export RUN_ID reviewer-a.jsonl
   cp reviewer-a.jsonl reviewer-b.jsonl
   ```
5. Each human reviewer independently fills only the `decision` object in their own copy.
6. Import decisions after both independent files are complete:
   ```bash
   php bin/privacy-evidence review:import reviewer-a.jsonl
   php bin/privacy-evidence review:import reviewer-b.jsonl
   ```
7. Compute agreement before adjudication.
8. Preserve pre-adjudication decisions; adjudication adds a new auditable record.
9. Freeze/hash the final-evaluation partition before using it for detector performance claims.

AI suggestions use reviewer type `ai_suggestion` and never satisfy the independent-human requirement.

## Required detector metrics

Per signal:

- TP, FP, TN, FN;
- precision;
- recall/sensitivity;
- F1;
- support;
- specificity/NPV where useful;
- abstention/unknown coverage where applicable.

Do not report accuracy alone for imbalanced labels. Unknown/unavailable/not-applicable states must not be silently collapsed into negative.

## Agreement

Use the statistic justified by the actual annotation scale and reviewer count. For two human reviewers and nominal labels, Cohen's kappa is a candidate; for broader missingness/multiple raters, Krippendorff's alpha may be preferable. Report per-category support and prevalence limitations.

## Traceability

Every evaluated label must trace to:

- ResearchRun;
- resource/document/browser artifact;
- evidence id/type;
- detector/version;
- annotation-handbook version;
- reviewer type/id/time/rationale;
- partition/version/hash.

