# Dependency update policy

Privacy Evidence uses **Dependabot** as the single owner of routine dependency-update pull requests.

Covered ecosystems:
- Composer at repository root;
- GitHub Actions;
- npm for the Playwright worker under `browser/`.

Dependabot runs weekly. Security alerts and automated security fixes remain GitHub security features and are independent from routine version-update ownership.

Dependency pull requests must run the same deterministic CI checks as ordinary pull requests. Tool versions should remain visible in Composer/npm/workflow metadata so dependency automation can discover them.

Do not introduce Renovate while Dependabot owns these ecosystems unless an ADR explicitly changes the policy. If the policy changes, remove overlapping automation before enabling the replacement.

Held or ignored versions require an inline rationale in dependency configuration or an ADR when the constraint is architectural.


## Experimental PHP-native ML dependencies

The PHP-native ML experiment uses exact RC constraints for `rubix/ml` and `rubix/tensor`; the root
requirements and `composer.lock` both pin `3.0.0-rc4` and `4.0.0-rc2`
respectively, and those versions are recorded in the experimental backend metadata.

The explicit Tensor root constraint is intentional: Rubix ML 3.0.0-rc4
requires the pre-release Tensor 4.0.0-rc2, while the repository keeps
`minimum-stability: stable` for all other dependencies. Do not relax the
repository-wide stability policy merely to install the experiment.

Both versions are recorded in experimental backend metadata. Upgrades require
rerunning the deterministic Rubix smoke test and the project quality suite.
Until a stable Rubix 3 release is evaluated, these packages remain an
experimental infrastructure dependency and must not alter deterministic
acquisition or evidence semantics.
