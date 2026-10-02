# Deterministic crawl and scheduling policy

Policy version: **0.1.0-draft**

## Scope

The crawler seeks privacy-relevant evidence, not exhaustive site archiving.

## Ordering

Candidate URLs are normalized and processed in deterministic priority/order.

Priority groups:
1. explicit privacy/data-protection paths or link text;
2. cookie/data-rights/DPO/contact candidates;
3. about/legal/terms pages;
4. other same-origin pages discovered within budget.

Within equal priority, order lexicographically by normalized URL.

## Origin policy

Default crawl follows HTTP/HTTPS links on the same registrable host/origin policy defined in configuration. Cross-origin resources are recorded as references but are not recursively crawled unless explicitly enabled by a detector/profile requirement.

## Stop/budget semantics

Limits are explicit terminal reasons, never silent truncation:
- max pages;
- max depth;
- max bytes;
- max elapsed time;
- max browser pages.

## Retry

Only transient network/server failures are retry-eligible. Deterministic invalid URL, blocked private network and unsupported scheme failures are not retried.

## robots.txt

The project records and respects configured robots policy for ordinary crawling. Research exceptions, if ever introduced, require a protocol/ethical decision and cannot be hidden in implementation code.

## Provenance

Every scheduled/skipped URL records the rule/reason that produced the decision.
