<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Manuscript evidence map

Map version: **0.1.0**

This file prevents manuscript claims from drifting away from executable evidence.

Legend:
- **READY** — frozen evidence exists and can support writing;
- **PARTIAL** — some evidence exists but the declared analysis is incomplete;
- **BLOCKED** — human/external/future execution required;
- **DO NOT CLAIM** — explicitly unsupported.

| Manuscript claim/result | RQ | Governing issue(s) | Required artifact/evidence | Status |
|---|---|---|---|---|
| central problem/contribution framing | all | #185 | `research-contribution.md` | READY |
| paper RQs and operational trace | all | #186 | `research-model.md/yaml` | READY |
| methodological stack | all | #187 | `methodology.md`, `standards-compliance.md` | READY |
| existing privacy tools overlap | related work | #189/#190 | `tool-landscape-review.md`, `novelty-audit.md` | READY |
| WEC comparative result | supporting | #191 | frozen comparison protocol/results | BLOCKED |
| source-to-measurement attrition counts | RQ1 | #192 | `docs/research/results/ipb-2026-10-05-attrition-summary.json`, preserved full-run `population-results.json` + hashes | READY |
| attrition flow figure | RQ1 | #192 | `docs/research/results/ipb-2026-10-05-attrition.md`, `ipb-2026-10-05-attrition-flow.csv` | READY |
| missingness changes estimates | RQ2 | #193 | `docs/research/results/ipb-2026-10-05-missingness-sensitivity.{json,csv,md}` | READY |
| measurability is/is not selective | RQ1/discussion | #194 | `docs/research/results/ipb-2026-10-05-selective-measurability.{json,csv,md}` | READY |
| reviewer UI is usable/equivalent | RQ4 method | #195 | real pilot record + frozen UI version | BLOCKED |
| final detector evaluation sample supports claims | RQ4 | #196 | sample-size rationale + frozen hashes | BLOCKED |
| two independent human reviews | RQ4 | #32/#33/#84/#178 | imported reviewer A/B packages | BLOCKED |
| inter-rater agreement | RQ4 | #33 | frozen pre-adjudication agreement report | BLOCKED |
| detector precision/recall/F1 | RQ4 | #84 | adjudicated reference + evaluation output | BLOCKED |
| deterministic clean-checkout reproduction | RQ3 | #87 | acceptance report | BLOCKED |
| publication-grade replication package | RQ3 | #199 | frozen package/tag/hash/DOI | BLOCKED |
| independent non-implementer reproduction | RQ3 | #200 | independent reproduction report | BLOCKED |
| transferability to second population | RQ5 | #201 | second-population protocol/run/analysis | BLOCKED |
| Privacy Evidence is first privacy scanner | — | #190 | contradicted by prior art | DO NOT CLAIM |
| Privacy Evidence proves legal compliance | — | protocol | outside construct | DO NOT CLAIM |
| PHP is superior/equivalent to Python generally | — | no comparative study | no evidence | DO NOT CLAIM |

## Writing rule

Before adding a numerical result to the manuscript:
1. identify its row/artifact here;
2. verify the exact ResearchRun/version/hash;
3. generate the number from machine-readable outputs;
4. cite the result source in the paper's reproducibility notes;
5. update this map to READY.

If a result is manually copied from an old blog post, chat, screenshot or memory, it is not manuscript evidence.
