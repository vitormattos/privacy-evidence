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

## Hosted-runner Chromium preflight

The 2026-10-08 attempt (Actions run `37723853611`) is **infrastructure-invalid**, not a WEC coverage result. All 12 WEC processes exited 1 after writing `inspection.json`. The preserved stderr shows that the primary browser was launched with `--no-sandbox` and reached "Done: Ending session", but an additional Chromium launched later failed with "No usable sandbox". Neither a written JSON file nor a completed primary browser phase overrides the nonzero tool exit in the frozen comparison.

On GitHub-hosted Ubuntu, the execute workflow now points Puppeteer's executable to a minimal ephemeral wrapper that adds the same `--no-sandbox` argument to **both** Chromium launches. Before visiting the declared sample, it runs the exact WEC command against a synthetic loopback-only page and requires a zero process exit plus a parseable `inspection.json`. This preflight is an environment test, not a thirteenth study target. It neither bypasses target TLS checks nor modifies the frozen sample or shared metrics.

Do not reinterpret the invalid 2026-10-08 artifact as successful collection. Re-run `phase=execute` after the fix merges. If the synthetic preflight fails, preserve the failure diagnosis and repair infrastructure before repeating the frozen observation. This infrastructure repair does not itself change protocol 0.1.0; any change to target or outcome semantics still requires a versioned protocol amendment.

## Post-execution gate

Do not interpret results directly from raw logs. After execution, inspect WEC 3.3.0's actual output schema, build a normalization step that preserves tool-native semantics, keep unknown/non-equivalent fields null rather than false, create the one-row-per-target/tool comparison table, publish only non-restricted normalized/aggregate results, and update #191 with the exact workflow run, hashes, deviations and conclusions.

Raw website artifacts remain outside the public repository under the data-policy boundary.

## 2026-11-15 execution-accounting diagnosis

Actions run [43721090176](https://github.com/vitormattos/privacy-evidence/actions/runs/43721090176)
on PE commit `36985234008b2da5e390dff3cfccbaab314e3e43` reached both tool blocks.
The restricted artifact (ID `14164214102`) contains all 12 WEC per-target
`exit-code.txt` files, each with **0**, and 12 parseable native
`inspection.json` files. Privacy Evidence also completed its ResearchRun and export.

The workflow failed **after** WEC collection because the shell's failure-count
`find -exec sh -c` command returned 1 when every exit code was zero.
With `set -o pipefail`, the assignment itself caused the step to exit before
writing summary counts or deviations. This is an orchestration accounting defect,
not a WEC negative scientific finding. A second provenance defect allowed
`files.sha256` to hash itself while being written; the old per-target manifests
must not be presented as verified artifact checksums.

The repair uses a dedicated deterministic account/hash script, tests all-pass,
mixed, all-fail, missing and malformed exit cases, and excludes the hash manifest
from its own verified input set. It also normalizes both tool-native outputs **only
after** complete execution, requiring all 12 declared target identities to reconcile.

The restricted normalized comparison package is written under
`data/experiments/wec-baseline-v1/normalized/` with:
`comparison.json`, `comparison.csv`, `summary.json`, and `report.md`.
These contain declared URLs and artifact references and are **not** automatically
approved for public release. Only separately reviewed non-restricted aggregates
may be published in the repository.

Operational non-equivalence must remain visible: PE's measured/partially measured/
not measurable states and successful document counts are not the same measurement
as WEC's zero process exit with parseable browser inspection. A WEC HTTP error
in native diagnostics can coexist with a zero exit. Cookie/third-party detector
signals and browser-jar/request fields are not forced into a false equivalence.
The existing harness does not provide PE per-target wall times; the normalizer
records that limitation instead of inventing them.

**Security boundary:** the pre-existing GitHub-hosted WEC runner uses Chromium
`--no-sandbox`. This remediation does not weaken TLS validation or browser
configuration further, but the browser sandbox is not enabled in that setup.
Do not claim sandbox-level isolation for those WEC processes or use this workflow
for authenticated/private targets. The next security hardening should evaluate
a sandbox-capable, isolated runner before any expanded or less-trusted sample.
This is distinct from research measurement validity and must be reported openly.

The 2026-11-15 failed run remains a diagnostic artifact, not an accepted experiment.
A new `phase=execute` on an identified current-main commit, inspection of the
restricted normalized output, and explicit acceptance/report review are required
before closing #191.
