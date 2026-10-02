# AGENTS.md

## Project purpose

Privacy Evidence is research software for reproducible collection and analysis of publicly observable privacy evidence. Treat software behavior as part of the measurement instrument.

## Non-negotiable invariants

- Never equate website evidence with organization-level legal compliance.
- Keep observation, evidence classification, regulatory mapping, human adjudication and research inference as separate layers.
- Preserve source values and provenance before normalization.
- Never silently discard malformed, unavailable or unknown observations.
- Do not disable TLS verification to make collection succeed.
- Do not allow ordinary crawler/browser jobs to access loopback, private, link-local or cloud-metadata networks.
- Deterministic tests must not depend on live public websites.
- Raw research data, screenshots, cookies, storage dumps and unrelated personal data must not be committed accidentally.
- Methodological changes must be versioned independently from software releases.
- Changes to detector semantics require tests and, once available, evaluation against the project gold dataset.
- Regulatory mappings must cite authoritative sources and must identify non-observable obligations explicitly.

## Repository structure

- `src/`: production code.
- `tests/Unit/`: isolated deterministic tests.
- `tests/Integration/`: controlled integration tests.
- `tests/Fixtures/`: synthetic or intentionally versioned deterministic fixtures.
- `docs/research/`: research protocol and measurement definitions.
- `docs/regulatory/`: regulatory profile specifications.
- `docs/architecture/`: architecture and pipeline documentation.
- `docs/adr/`: durable architecture decisions.
- `schema/`: machine-readable schemas.
- `data/`: local research artifacts; most contents are intentionally ignored by Git.

## Change workflow

Before implementing a change:

1. Identify the governing issue.
2. Determine whether the change affects software only or also protocol, detector, profile, schema or annotation semantics.
3. Read the relevant canonical docs/ADR.
4. Add or update tests before behavior-changing refactors where practical.
5. Keep network/browser behavior bounded and auditable.

Before submitting a pull request, run the repository's Composer quality commands once they are available.

## Documentation rules

README is a product/research landing page. Do not turn it into a technical manual. Put implementation detail in `docs/` or `CONTRIBUTING.md`.

Do not duplicate canonical rules across many files. Link to the canonical document.

## Agent Skills

Project-specific Skills may orchestrate stable workflows later, but they must not replace these repository rules, CI or canonical documentation.
