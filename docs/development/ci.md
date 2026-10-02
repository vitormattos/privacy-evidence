# Continuous integration policy

The default CI path is deterministic and must not crawl live public websites.

## Required merge gates

Stable job names:

- Composer validate and audit
- PHPUnit unit
- PHPUnit integration
- Psalm
- PHPStan
- PHPCS
- REUSE lint

A failure in any required gate blocks merge. Dependency-audit failures require remediation or an explicit, time-bounded documented exception.

Mutation testing is a secondary/manual or scheduled gate because of runtime cost. It informs test-strength improvements and does not replace empirical detector validation.

## Local equivalent

Run `composer quality` and `reuse lint`. Browser worker changes additionally validate the npm package and Node syntax.

## Workflow maintenance

Workflow permissions remain least-privilege. Tool versions stay visible in Composer, npm, or action metadata so dependency automation can update them. Network-dependent research runs do not belong in default pull-request CI. Stable job names are part of branch-protection configuration and should change only deliberately.
