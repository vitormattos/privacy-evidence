# Repository protection policy

The default branch is `main`.

Once stable CI job names exist, protect `main` with:
- pull request required before merge;
- one approving review;
- conversation resolution required;
- force pushes disabled;
- branch deletion disabled;
- required status checks:
  - Composer validate and audit
  - PHPUnit unit
  - PHPUnit integration
  - Psalm
  - PHPStan
  - PHPCS
  - REUSE lint
  - Container smoke

CODEOWNERS identifies Vitor Mattos as the current owner for the repository and research-sensitive paths.

Dependency automation may open pull requests but must satisfy the same required checks. Administrative bypass should be reserved for recovery from repository configuration failures and documented when used.
