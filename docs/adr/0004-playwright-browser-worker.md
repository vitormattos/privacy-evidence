# ADR 0004 — Playwright as isolated browser worker

Status: Accepted for initial implementation
Date: 2026-10-02

## Context

The browser backend must support rendered DOM, cookies/storage, network observations and interaction. Symfony Panther/WebDriver is convenient from PHP but exposes less first-class network/browser-context instrumentation. Direct Chrome DevTools wrappers increase custom protocol work.

## Decision

Use Playwright in an isolated Node.js worker/provider for the initial behavioral-browser backend. PHP remains the orchestrator.

The provider boundary remains replaceable; Panther or another backend can be evaluated later without changing evidence contracts. The comparative capability review and reproducible benchmark protocol are recorded in `docs/research/browser-backend-evaluation.md`.

## Decision criteria

- browser context isolation;
- cookies/local/session storage access;
- network request observation;
- deterministic scripted interaction;
- active upstream maintenance;
- browser-version management;
- low coupling to PHP domain logic.

## Consequences

- Node/npm is an optional runtime dependency for browser jobs;
- browser workers can be constrained/recycled independently;
- Dependabot monitors npm if this worker is installed.
