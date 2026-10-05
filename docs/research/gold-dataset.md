# Gold dataset and reliability workflow

Protocol version: **1.0.0**

This document defines the machine-preparable portion of the gold-dataset workflow. Actual labels designated as human ground truth must be produced by actual human reviewers.

## Deterministic sampling

Generate a package after a ResearchRun has persisted evidence:

```bash
bin/privacy-evidence review:sample RUN_ID data/restricted/gold/run.json \
  --per-stratum=2 \
  --seed=privacy-evidence-gold-v1
```

The sampler stratifies by evidence type, automated observation state, and review-needed status. Within each stratum, selection is deterministic from the seed and evidence id. This deliberately includes positive, negative and uncertain/review cases where they exist.

The resulting package records its run id, sampler seed, handbook version and evidence provenance. The package is a restricted research artifact by default.

## Development and final evaluation separation

Do not tune detector rules, prompts, thresholds or models using the final evaluation package.

For a production evaluation:
- create a development/tuning partition first;
- freeze detector behavior;
- generate a distinct final-evaluation package with a separately recorded seed/version;
- freeze the final package hash before human labels are inspected by detector authors.

For the initial PoC, a smaller reliability subset may be used, but it must be identified explicitly as PoC evidence rather than a production-quality benchmark.

## Independent human review

Give identical blank copies of the selected package to at least two humans. Prefer the self-contained `review:html` interface so reviewers do not edit JSON directly and automated classifier metadata is not visually emphasized. Each reviewer fills:
- `humanState`;
- `rationale`;
- optionally `reviewedAt`.

Import separately:

```bash
bin/privacy-evidence review:import reviewer-a.json reviewer-a
bin/privacy-evidence review:import reviewer-b.json reviewer-b
```

Reviewer ids are stable pseudonymous identifiers. An id beginning with `ai:` is rejected for human imports.

The storage layer enforces one immutable decision per run/evidence/reviewer identity.

## Agreement

Before adjudication:

```bash
bin/privacy-evidence review:agreement RUN_ID reviewer-a reviewer-b
```

The initial protocol uses Cohen's kappa per evidence type because the designated reliability subset has exactly two independent human reviewers and nominal observation states. Output always includes pair count, raw observed agreement, chance-expected agreement, kappa and observed categories.

Kappa can be null when chance agreement is mathematically 1.0; that case must be reported rather than coerced.

## Adjudication

Only after agreement is frozen, resolve disagreements using the annotation handbook. Preserve original reviewer records unchanged. Import the final adjudicated package under a separate stable human reviewer id such as `adjudicator-01`.

## Detector evaluation

```bash
bin/privacy-evidence review:evaluate RUN_ID adjudicator-01 gold-v1
```

This produces per-signal confusion matrices, precision, recall, F1, support, abstention/coverage, and traceable false-positive/false-negative cases.

## Publication and privacy

Raw annotation packages belong under restricted data unless the data policy explicitly classifies them as publishable. Public replication packages should minimize excerpts and personal data while preserving stable evidence/artifact identifiers and derived metrics.


## Reviewer experience

Generate an offline browser interface for each blank package:

```bash
bin/privacy-evidence review:html reviewer-a.json reviewer-a.html
bin/privacy-evidence review:html reviewer-b.json reviewer-b.html
```

The generated page:
- sends no annotation data to a server;
- autosaves progress locally in the reviewer's browser;
- groups cases by evidence type to reduce cognitive switching;
- hides automated state/confidence from the normal decision view to reduce anchoring bias;
- embeds concise handbook guidance;
- supports keyboard labels and concise rationale helpers;
- estimates remaining time from the reviewer's observed pace;
- exports the original JSON package with only human review fields filled.

The canonical package and import semantics remain unchanged.


### Annotation-packet sufficiency

A human label is valid only when the reviewer can inspect enough preserved evidence to support the decision. The reviewer UI must not encourage a binary `present`/`absent` decision when the annotation packet contains no preserved excerpt or equivalent reviewable evidence. Such cases remain explicit as `unknown`/`unavailable` or another justified non-binary state until richer preserved context is available.
