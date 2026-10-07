# WEC baseline execution runbook

This runbook operationalizes the frozen protocol in `docs/research/wec-baseline-protocol.md`.

## Phase 1 — freeze external dependency identity

Run the **Controlled WEC baseline** workflow with `phase=freeze`.

The workflow verifies the committed SHA-256 of `examples/poc/sites.csv`, downloads the exact WEC 3.3.0 distribution declared in `config/experiments/wec-baseline-v1.json`, computes its SHA-256, records the current Privacy Evidence commit and Node/npm environment, and publishes only the freeze manifest. It does **not** visit study targets in this phase.

Copy the reported WEC SHA-256 into `wec.distributionSha256` in the experiment config and merge that change before execution.

## Phase 2 — execute

Run the same workflow with `phase=execute`.

Execution is refused if the sample hash differs, the WEC package hash is absent from config, or the downloaded package differs from the committed hash.

The harness records environment versions, chooses complete-tool block order deterministically from seed `wec-baseline-v1`, runs both tools over the unchanged 12-target sample, preserves per-target WEC exit codes/stdout/stderr/output hashes, preserves Privacy Evidence run/export provenance, and uploads experiment state as a restricted short-lived Actions artifact.

Block-level execution is an explicit protocol deviation from preferred per-target alternation, but it is permitted by the frozen protocol and recorded in the generated deviations file.

## Post-execution gate

Do not interpret results directly from raw logs. After execution, inspect WEC 3.3.0's actual output schema, build a normalization step that preserves tool-native semantics, keep unknown/non-equivalent fields null rather than false, create the one-row-per-target/tool comparison table, publish only non-restricted normalized/aggregate results, and update #191 with the exact workflow run, hashes, deviations and conclusions.

Raw website artifacts remain outside the public repository under the data-policy boundary.
