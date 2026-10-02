# ADR 0005 — SQLite-first metadata/checkpoint storage

Status: Accepted for initial PoC
Date: 2026-10-02

## Context

The initial PoC needs durable jobs, reviews and run metadata without introducing a network service as a prerequisite. Large production studies may later need PostgreSQL/Redis.

## Decision

Use SQLite for the initial local metadata/review/checkpoint implementation behind interfaces. Heavy immutable web artifacts use content-addressed filesystem storage.

## Consequences

- clean PoC setup remains small;
- transactions support restart/resume;
- interfaces must not expose SQLite-specific semantics so a distributed backend can replace it after benchmark evidence.
