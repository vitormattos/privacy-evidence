# Quality gates and static analysis

Privacy Evidence treats deterministic quality checks as part of the research instrument.

## PHPUnit

- `composer test:unit`: isolated deterministic tests.
- `composer test:integration`: controlled SQLite/process/HTTP-boundary tests.
- Default suites must not require live public websites.

## Psalm

Configuration: `psalm.xml`.

Policy:
- target strictness is Psalm error level 1;
- there is currently no broad baseline file;
- findings should be fixed rather than accumulated into a permanent baseline;
- if a temporary baseline becomes unavoidable, it must be finite, reviewed, linked to an issue and configured to fail on unused baseline entries so the ratchet only moves toward zero;
- new violations fail the dedicated CI job.

## PHPStan

Configuration: `phpstan.neon`.

Policy:
- level `max` is the target and current configuration;
- there is no global ignore-errors baseline;
- any narrowly necessary ignore must document the specific false positive or external typing limitation;
- new violations fail the dedicated CI job.

Psalm and PHPStan intentionally overlap. Keep both while they provide distinct actionable findings. Re-evaluate the duplication when maintenance cost exceeds demonstrated signal; removal requires an ADR or documented quality-policy change.

## PHPCS

Configuration: `phpcs.xml`.

The project uses PSR-12 over `src/` and `tests/`. Vendor, generated data and research artifacts are outside the configured scan roots rather than being hidden through broad exclusions.

Run:

```bash
composer phpcs
```

## Mutation testing

Configuration: `infection.json5`.

Mutation testing targets production code and is a secondary quality signal. Mutation-score thresholds are not a substitute for detector validation against reviewed research data.

## CI mapping

The CI workflow provides separately named jobs for Composer validation/audit, unit tests, integration tests, Psalm, PHPStan, PHPCS and REUSE. Dependency/tool versions are declared in Composer/npm/workflow metadata so update automation can discover them.


## Validation PR

The repository periodically uses a documentation-only validation pull request to exercise all deterministic PR quality gates against the current default-branch state. This does not replace normal feature-level testing; it is a diagnostic check for the CI configuration itself.
