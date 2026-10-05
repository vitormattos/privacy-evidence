# Deterministic crawl and scheduling policy

Policy version: **0.1.0-draft**

## Scope

The crawler seeks privacy-relevant evidence, not exhaustive site archiving.

## Ordering

Candidate URLs are normalized and processed in deterministic priority/order. Known tracking parameters are discarded for crawl deduplication while functional query parameters are preserved. If the same URL is discovered more than once, the highest-priority discovery wins.

Priority groups:
1. explicit privacy/data-protection paths or link text;
2. cookie/data-rights/DPO/contact candidates;
3. about/legal/terms pages;
4. other same-origin pages discovered within budget.

Within equal priority, order lexicographically by normalized URL.

## Origin policy

Default crawl follows HTTP/HTTPS links on the same host, treating the apex host and its `www.` alias as equivalent. Other subdomains and cross-origin resources are not recursively crawled unless explicitly enabled by a detector/profile requirement.

## Stop/budget semantics

Limits are explicit terminal reasons, never silent truncation:
- max pages;
- max depth;
- max bytes;
- optional max active wall-clock window;
- max browser pages.

The default wall-clock duration limit is disabled (`maxDurationSeconds = 0`). Queue wait is not evidence about the target website and therefore must not make a resource hit a crawl-duration limit merely because a large population is being processed concurrently. Pages, bytes and depth remain hard bounds.

## Retry

Only transient failures are retry-eligible. Deterministic invalid URL, NXDOMAIN/no A-or-AAAA result, blocked private network, TLS validation failures and unsupported scheme failures are not retried.

HTTP 429/502/503/504 responses use bounded retry with exponential fallback delay and `Retry-After` when supplied. A retry delay applies to pending jobs for the same host so one throttled endpoint does not trigger a burst from sibling jobs.

A failed legacy `www.` DNS name may be retried once against the same apex domain; failed HTTP transport may be retried once over HTTPS. These fallbacks preserve the original requested URL in provenance.

## robots.txt

The project records and respects configured robots policy for ordinary crawling. Research exceptions, if ever introduced, require a protocol/ethical decision and cannot be hidden in implementation code.

## Provenance

Every scheduled/skipped URL records the rule/reason that produced the decision.
