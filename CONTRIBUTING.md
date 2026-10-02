# Contributing to Privacy Evidence

Privacy Evidence is both software and a research instrument. Contributions are reviewed for implementation correctness **and** for their effect on measurement semantics.

## Before starting

Use an existing issue when possible. For significant architecture, methodology, detector or regulatory-profile changes, create or update the corresponding issue before implementation.

## Development principles

- Preserve raw observations and provenance.
- Prefer deterministic, independently testable components.
- Keep source acquisition separate from analysis.
- Keep generic evidence separate from regulatory interpretation.
- Avoid live-network dependencies in the default test suite.
- Treat performance and memory use as correctness concerns for large-scale runs.
- Do not introduce browser automation when static acquisition is sufficient.

## Research-impact review

A pull request must state whether it changes any of the following:

- research protocol;
- observable variable definition;
- detector behavior;
- regulatory mapping;
- annotation rules;
- schema/data semantics;
- denominator/aggregation logic;
- crawl/browser behavior that may affect observations.

If yes, the relevant version must be updated according to the project's versioning policy once established.

## Tests

New parsing, normalization, classification and metric logic requires focused unit tests.

Network/database/browser boundaries should use controlled integration tests.

Changes that fix legacy behavior should first document the previous behavior through characterization tests where practical.

## Personal and research data

Do not commit real research datasets, screenshots, cookie stores, browser profiles, databases or unrelated personal data unless the repository explicitly designates the artifact as publishable and licensed.

Use synthetic or minimized fixtures whenever possible.

## Security

Crawler and browser inputs are untrusted. Follow `SECURITY.md` and the network-safety threat model. Do not weaken TLS or private-network protections to make tests or crawls pass.

## Pull requests

A pull request should explain:

- motivation and linked issue;
- behavior changed;
- tests added/updated;
- methodological impact;
- schema/backward-compatibility impact;
- privacy/data impact;
- security impact;
- performance impact;
- documentation/ADR impact.

Detailed development commands will be maintained in `docs/development/` as the implementation stabilizes.
