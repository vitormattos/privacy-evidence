# Mutation testing

Privacy Evidence uses Infection as a secondary quality signal for critical parsing, normalization, evidence and crawl logic.

## Baseline

The validated baseline on 2026-10-02 produced an MSI of **49.66%** on the configured critical scope.

The repository uses a minimum MSI ratchet of **45%**. This is intentionally below the observed baseline so ordinary refactors do not create a permanently failing scheduled workflow while still preventing large regressions.

The threshold is not a quality target. New surviving mutants in critical decision rules should result in one of:

1. a focused test that kills the mutant;
2. a documented rationale explaining why the mutant is equivalent or outside the intended behavior.

Raise the ratchet when sustained test improvements make a higher floor stable.

Mutation testing is manual/scheduled and path-triggered on main. It is not a required gate for every pull request.
