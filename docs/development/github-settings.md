# GitHub repository settings

The repository files describe the intended policy, but GitHub administration settings are external state. Reproduce them with GitHub CLI rather than relying on undocumented UI clicks.

## Security features

```bash
REPO="vitormattos/privacy-evidence"

gh api --method PUT "repos/$REPO/vulnerability-alerts"
gh api --method PUT "repos/$REPO/automated-security-fixes"
gh api --method PUT "repos/$REPO/private-vulnerability-reporting"
```

## Protect main

Run after the stable CI job names in `.github/workflows/ci.yml` exist:

```bash
REPO="vitormattos/privacy-evidence"

gh api   --method PUT   -H "Accept: application/vnd.github+json"   "repos/$REPO/branches/main/protection"   --input - <<'JSON'
{
  "required_status_checks": {
    "strict": true,
    "contexts": [
      "Composer validate and audit",
      "PHPUnit unit",
      "PHPUnit integration",
      "Psalm",
      "PHPStan",
      "PHPCS",
      "REUSE lint",
      "Container smoke"
    ]
  },
  "enforce_admins": true,
  "required_pull_request_reviews": {
    "dismiss_stale_reviews": true,
    "require_code_owner_reviews": true,
    "required_approving_review_count": 1
  },
  "restrictions": null,
  "required_conversation_resolution": true,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "block_creations": false,
  "required_linear_history": false,
  "lock_branch": false,
  "allow_fork_syncing": true
}
JSON
```

Do not add Mutation testing or Performance benchmark as required PR checks. They are secondary/scheduled experimental signals and can be materially more expensive.

## Verification

```bash
gh api "repos/$REPO/branches/main/protection"
gh api "repos/$REPO"
```

Keep this document synchronized with CI job names and CODEOWNERS.
