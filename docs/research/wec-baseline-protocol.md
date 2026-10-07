# Controlled WEC baseline protocol

Protocol version: **0.1.0**

Governing issue: #191

Status: **pre-execution freeze candidate**. Do not collect comparison results until this protocol is merged.

## Purpose

This experiment compares Privacy Evidence with the European Data Protection Supervisor (EDPS) Website Evidence Collector (WEC) on the same deliberately heterogeneous public-website sample.

It is not a generic "which tool is better" benchmark. The comparison tests specific unresolved claims from `novelty-audit.md` about population accounting, failure/non-observability semantics and provenance.

## Comparison questions

### CQ1 — Input accountability

For the same declared input list, can each tool account for every input with an explicit terminal collection outcome?

### CQ2 — Non-observability semantics

When collection does not yield analyzable evidence, what machine-readable state/reason is preserved, and can a later analyst distinguish failure/unavailability from observed absence?

### CQ3 — Evidence provenance

For comparable browser-observable evidence (cookies, first-/third-party requests and acquired page material), what identifiers and metadata link the reported observation back to its collection artifact/session?

### CQ4 — Reproducibility metadata

Which tool/configuration/runtime/version fields are preserved strongly enough to explain or repeat the collection setup?

### CQ5 — Transformation cost

How much additional deterministic processing is required to transform each tool's native outputs into a per-input research table suitable for population accounting and later human review?

CQ5 measures transformation steps/artifacts, not subjective developer convenience.

## Tools and versions

### WEC

Pin **Website Evidence Collector 3.3.0**.

Authoritative project:
- https://code.europa.eu/EDPS/website-evidence-collector
- https://www.edps.europa.eu/data-protection/technology-monitoring/data-protection-and-privacy-tools_en

Release record:
- https://interoperable-europe.ec.europa.eu/collection/free-and-open-source-software/solution/website-evidence-collector/release/330

The release is installed from the exact v3.3.0 distribution, and the downloaded package SHA-256 must be recorded before execution.

WEC 3.3.0 requires Node.js 20 or later and uses Chromium/Puppeteer. The execution record must capture the actual Node, npm, WEC and browser versions.

### Privacy Evidence

Use the exact repository commit that contains this merged protocol plus the then-current analysis implementation. Record:
- Git commit;
- PHP version;
- Composer lock hash;
- protocol/schema/detector/profile versions;
- effective configuration;
- browser/runtime version when browser escalation occurs.

Do not update either tool during the comparison window.

## Common sample

Use the 12 public targets already versioned in:

`examples/poc/sites.csv`

The sample is intentionally heterogeneous and is **not a probability sample**. It exists to exercise differing website/runtime conditions and to compare measurement semantics, not to estimate population prevalence.

The exact CSV SHA-256 and ordered rows must be copied into the experiment manifest before execution. Do not replace failing sites after collection begins. A site that becomes unavailable is a result, not a reason to redraw the sample.

## Observation window

Run both tools in the same environment and as close in time as practical.

Required order:
1. randomize tool order per target deterministically using seed `wec-baseline-v1`;
2. run the assigned first tool;
3. run the second tool immediately after;
4. record start/end timestamps for both.

The deterministic alternation prevents one tool from systematically running first across all sites while avoiding simultaneous requests that could interfere with one another.

If the execution harness cannot alternate safely, run complete tool blocks in an order chosen by the same seed and record that limitation.

## Network and interaction condition

Primary condition:
- fresh browser/session state per target where the tool supports it;
- no authentication;
- no manual consent interaction;
- no attempt to bypass bot protection;
- no TLS-verification weakening;
- no retry that changes semantic configuration.

Retries are allowed only for infrastructure failures outside the target/tool semantics (for example, local process startup failure) and must remain separately logged.

## Comparable unit

The common unit is **one declared start URL**.

Privacy Evidence may discover/crawl additional pages and WEC may collect tool-specific navigation material. Those extras are preserved but are not used to pretend that the two tools have identical crawl semantics.

Shared comparisons are made at the declared-target level.

## Common comparison table

Produce one row per target/tool with at least:

- `target_id`;
- `declared_url`;
- `tool`;
- `tool_version`;
- `run/session_id`;
- `started_at`;
- `finished_at`;
- `terminal_state`;
- `terminal_reason`;
- `collection_succeeded`;
- `cookies_observable`;
- `third_party_requests_observable`;
- `artifact_or_session_reference`;
- `artifact_hash_available`;
- `config_version_available`;
- `tool_version_preserved`;
- `runtime_version_preserved`;
- `raw_output_path`;
- `normalization_notes`.

Unknown or non-equivalent fields remain null/explicitly marked; they are never coerced to false.

## Metrics

### M1 — Declared-input accounting

`terminal_rows / declared_targets`

Report counts and reasons, not only a percentage.

### M2 — Successful collection coverage

Per tool:
`collection_succeeded / declared_targets`

This is not an accuracy metric.

### M3 — Explicit failure-state coverage

Among unsuccessful targets:
`targets_with_machine_readable_failure_reason / unsuccessful_targets`

### M4 — Shared evidence observability

For cookies and third-party-request evidence separately, report:
- observable;
- explicitly unavailable/error;
- not represented/non-equivalent.

Do not infer semantic equality from similarly named fields.

### M5 — Provenance-field coverage

For each declared provenance field, report `available / targets`.

### M6 — Reproducibility metadata coverage

Report whether tool version, runtime/browser version and effective configuration are recoverable from the preserved experiment package.

### M7 — Resource cost

Record wall-clock duration per target/tool. Record CPU/memory only if the same measurement method is available for both executions; otherwise omit rather than compare non-equivalent telemetry.

## Expected non-equivalences

The protocol expects and preserves these differences:

- WEC is designed primarily for browser evidence collection; Privacy Evidence includes source-population, classification, detector, review and regulatory layers.
- Privacy Evidence's privacy-policy/text detectors do not have to be mapped to WEC fields.
- WEC's detailed browser/network outputs do not have to be forced into Privacy Evidence evidence types.
- Different page-navigation strategies may yield different artifact counts.
- A field missing from reviewed documentation/output is `unknown/not represented in this setup`, not proof the tool cannot support it in another configuration.

## Failure handling

Each declared target remains in the normalized comparison dataset even if:
- DNS/TLS/HTTP fails;
- browser launch fails;
- a bot/challenge blocks collection;
- the tool exits non-zero;
- output is incomplete;
- a target changes or disappears during the observation window.

Preserve:
- exit code;
- stderr/stdout log;
- timestamps;
- tool-native error/status;
- normalized terminal reason;
- whether retry occurred and why.

## Raw/public data boundary

Raw website artifacts may contain cookies, page content or unrelated personal data and must remain outside the public repository unless separately approved by `data-policy.md`.

The public replication artifact may include:
- exact target list;
- tool/config versions;
- scripts/config;
- hashes/manifests;
- aggregate/normalized non-restricted comparison table;
- derived analysis/report.

## Analysis rules

- Report tool-native semantics before normalized interpretation.
- Do not convert unknown/unavailable to negative evidence.
- Do not call a collection failure a privacy failure.
- Do not infer legal compliance/non-compliance.
- Do not infer generic tool superiority.
- Separate descriptive differences from causal explanations.
- With 12 diagnostic targets, avoid significance testing on success-rate differences unless a method is justified after inspecting the paired design; descriptive paired outcomes are the default.

## Claim decision mapping

The comparison may update only the claims it directly tests:

- C1 population-accounting semantics;
- C2 measurement-loss semantics;
- C3 end-to-end evidence-chain inputs at the acquisition/artifact layer;
- C4 ResearchRun/provenance-bundle distinction.

It cannot establish C5 detector validity because that requires the independent human-reference workflow.

## Reproducibility package

The final #191 package must contain:
- this protocol version;
- exact sample file + SHA-256;
- deterministic execution-order manifest;
- WEC package hash/version;
- Privacy Evidence commit/version;
- environment manifest;
- execution scripts/config;
- raw-output hashes and restricted/public classification;
- normalized comparison CSV/JSON;
- analysis report;
- deviations log.

## Freeze rule

After this protocol is merged, changes to:
- sample;
- comparison questions;
- shared outcome semantics;
- metrics;
- retry/failure rules;

require a protocol version bump and must be declared before rerunning the experiment. Result-driven silent changes are prohibited.
