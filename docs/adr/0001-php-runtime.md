# ADR 0001 — PHP 8.4+ as the primary runtime

Status: Accepted
Date: 2026-10-02

## Context

Privacy Evidence requires a maintainable research orchestration layer, strong static analysis, modern Symfony components and long-running CLI workflows.

## Decision

Use PHP 8.4.1 or newer in the 8.4+ supported line as the primary application/runtime language, with Composer-managed dependencies.

Browser automation may use an isolated non-PHP worker when browser tooling is materially stronger outside PHP.

## Consequences

- Symfony 8.1 components are available.
- CI/container/runtime documentation must agree with Composer.
- Research logic remains testable independently from browser technology.
