# ADR 0002 — Regulation-agnostic evidence model

Status: Accepted
Date: 2026-10-02

## Context

The same public observation can be relevant to LGPD, GDPR and cookie/ePrivacy analysis. Encoding regulation names into detectors would duplicate collection and couple evidence to legal interpretation.

## Decision

Detectors emit generic `PrivacyEvidence`. Versioned regulatory profiles map evidence to requirements/guidance later.

## Consequences

- one crawl can support several regulatory profiles;
- legal applicability remains outside detector logic;
- adding a regulation normally adds mappings, not a second crawler;
- no detector emits a compliance verdict.
