# Continuous integration policy

The default CI path is deterministic and does not crawl live public websites.

Required stable merge gates are Composer validate/audit, PHPUnit unit, PHPUnit integration, Psalm, PHPStan, PHPCS and REUSE lint. A failure in any required gate blocks merge. Dependency-audit findings require remediation or an explicit, time-bounded documented exception.

Mutation testing is a secondary quality gate because of runtime cost. It complements, but does not replace, empirical detector validation.

Local equivalents are `composer quality` and `reuse lint`. Browser-worker changes also validate npm metadata and Node syntax.

Workflow permissions remain least-privilege. Dependency/tool versions remain visible to update automation. Stable CI job names are part of branch-protection configuration.
