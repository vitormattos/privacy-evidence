# ADR 0006 — Bounded stage-specific worker pipeline

Status: Accepted
Date: 2026-10-02

## Context

HTTP acquisition, text detection and browser automation have very different resource profiles. A single unbounded queue can exhaust memory or let browser jobs starve lightweight work.

## Decision

Represent work as idempotent stage jobs with explicit status/retry metadata and independent concurrency limits. HTTP/browser stages use separate configured limits. Queue capacity and crawl budgets provide backpressure.

For the initial PoC, a SQLite-backed queue is sufficient; a distributed transport can be introduced after benchmark evidence.

## Consequences

- interrupted runs can resume;
- job duplicate delivery must be safe;
- worker recycling limits long-lived memory growth;
- benchmark results determine default concurrency rather than intuition.
