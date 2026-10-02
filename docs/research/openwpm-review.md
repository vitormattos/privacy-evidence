# OpenWPM and web-measurement architecture review

## Why it matters

OpenWPM is a research-grade web-measurement platform used for large-scale browser studies. Its architecture provides relevant lessons for Privacy Evidence even though this project remains PHP-first.

## Adopt

### Browser-process isolation
Browser execution should occur in a separate worker class/pool so browser crashes or memory growth do not consume HTTP workers.

### Failure recovery
Browser jobs require explicit timeout/failure states and worker recycle/restart behavior.

### Structured vs unstructured artifacts
Metadata/evidence and heavy raw browser artifacts should have separate storage concerns.

### Visit/run identity
Every browser observation belongs to a ResearchRun/resource/document identity.

### Watchdogs and telemetry
Long runs need worker health, memory/resource telemetry and bounded restart semantics.

## Adapt

Privacy Evidence does not need OpenWPM's full Firefox WebExtension instrumentation for its initial scope. It should use a simpler browser backend capable of:
- rendered DOM;
- cookies/storage;
- network observations;
- interactions;
- screenshots where justified.

The browser provider remains behind an interface so richer instrumentation can be introduced later.

## Do not copy initially

- exhaustive tracking/fingerprinting instrumentation;
- million-site infrastructure;
- implementation-specific socket protocols;
- coupling to a particular browser vendor.

## Architecture consequence

The PHP application remains the research orchestrator. Browser execution is an isolated provider/worker with explicit JSON-like request/result contracts and bounded concurrency.

## Sources

- Englehardt & Narayanan (2016), DOI 10.1145/2976749.2978313.
- OpenWPM Platform Architecture: https://openwpm.readthedocs.io/en/stable/Platform-Architecture.html
- OpenWPM Architecture Internals: https://openwpm.readthedocs.io/en/stable/Architecture-Internals.html
