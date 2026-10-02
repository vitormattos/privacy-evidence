---
name: prepare-research-pr
description: Prepare or review a Privacy Evidence pull request using the repository's engineering, security, reproducibility and research-methodology gates. Use before opening, updating or merging a PR in this repository.
---

# Prepare research-grade pull requests

1. Read `AGENTS.md`, issue #88, linked issues and governing ADR/docs.
2. Inspect the complete diff for unrelated changes and research data.
3. Determine impact on protocol, detector, regulatory profile, schema, annotation handbook and aggregation semantics.
4. Run the relevant Composer quality commands and focused tests.
5. Run browser/npm checks when browser code changed.
6. Verify network-safety and data-minimization invariants.
7. Update docs/ADR/version identifiers where semantics changed.
8. Ensure no live public website is required by deterministic CI.
9. Complete the PR template with explicit research/security/performance impact.
10. Merge only after required CI checks pass; leave issue completion evidence after merge.
