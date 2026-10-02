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
