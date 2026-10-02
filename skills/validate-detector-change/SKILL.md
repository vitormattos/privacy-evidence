---
name: validate-detector-change
description: Validate Privacy Evidence detector changes against deterministic fixtures, reviewed gold data and research-versioning rules. Use when changing, reviewing or diagnosing evidence detectors, detector rules, thresholds or detector-related tests in this repository.
---

# Validate detector changes

1. Read `AGENTS.md`, issue #88, the detector issue, `docs/research/annotation-handbook.md`, `docs/research/evaluation.md`, and the relevant detector implementation/tests.
2. Identify the detector version and the evidence types it emits.
3. Run deterministic unit/integration tests before changing behavior.
4. Add a focused regression fixture for every bug or semantic change.
5. Run detector evaluation against the current development/gold dataset when available.
6. Report confusion matrix, precision, recall, F1, support and abstention/unknown handling per signal.
7. Trace every new false positive/negative to the exact evidence artifact.
8. If semantics changed, update detector/version metadata and research-impact documentation.
9. Never label a detector as proving LGPD/GDPR compliance.
10. Finish with the issue completion protocol from AGENTS.md.
