# ADR 0003 — HTTP-first acquisition with browser escalation

Status: Accepted
Date: 2026-10-02

## Context

Full browsers are significantly heavier than ordinary HTTP acquisition, while many relevant privacy pages are available statically.

## Decision

Attempt bounded HTTP acquisition and DOM analysis first. Escalate to a browser only when:
- the acquisition policy identifies a JavaScript application shell or insufficient rendered content;
- behavioral evidence is explicitly required, such as cookie/storage/network interaction.

Every escalation stores its reason.

## Consequences

- HTTP and browser concurrency are separately bounded;
- browser cost is measurable;
- browser availability does not define the generic evidence model.
