# Public scan workflow and anti-abuse/security specification

Specification version: **0.1.0**

Status: **concept/specification only**. This document does not authorize a public deployment.

This specification implements issue #203. It assumes the research/service boundary in `service-boundary.md` and the operational-vs-research data boundary in `technology-transfer-evaluation.md`.

## Product surface

The future service has two distinct entry modes.

### Anonymous single-site scan

Purpose: low-friction evaluation/lead-generation path.

Initial constraints:
- one submitted public HTTP(S) resource per job;
- no stored scan history or account dashboard;
- one active job per client IP;
- CAPTCHA/challenge may be required before enqueueing;
- no bulk/API access;
- short result/artifact TTL;
- no claim of legal compliance.

### Authenticated account

Purpose: history, higher quotas, recurring/bulk/API workflows and paid plans if later approved.

Account mode may add:
- multiple saved targets;
- job history;
- recurring schedules;
- bulk/API submission;
- team/tenant access;
- expert review.

Authentication does not weaken crawler/network safety limits.

## Request lifecycle

```text
submit URL
  -> parse/canonicalize
  -> authorize mode/plan
  -> anti-abuse challenge
  -> quota/cooldown check
  -> SSRF/preflight validation
  -> create immutable service job
  -> enqueue
  -> worker leases job
  -> invoke pinned Privacy Evidence engine
  -> enforce runtime budgets/egress policy
  -> persist service-safe summary + provenance
  -> expose status/result
  -> expire artifacts/logs by policy
```

All jobs are asynchronous. A request returning HTTP success means the job was accepted, not that the target is measurable or compliant.

## Initial safety quotas

These are conservative **safety defaults**, not pricing promises. #204 may propose different commercial entitlements only within the hard safety ceilings.

### Anonymous

- active jobs per IP: **1**;
- accepted jobs per IP: **3/hour** and **10/day**;
- same registrable domain: **1 accepted job / 30 minutes** globally;
- targets per job: **1**;
- recurring jobs: **none**;
- API: **none**.

### Authenticated

Pre-commercial default:
- active jobs per account: **2**;
- accepted jobs per account: **20/day**;
- same registrable domain per account: **1/15 minutes**;
- bulk submission: **maximum 20 targets/request**;
- maximum queued targets/account: **100**.

Higher future limits require measured resource-cost evidence and explicit service configuration.

### Global circuit breakers

Independent of plan:
- global concurrent browser jobs;
- queue depth;
- CPU/memory pressure;
- outbound-bandwidth budget;
- target-domain concurrency;
- error-rate/timeout spike thresholds.

The service must be able to stop accepting new work before workers become resource-exhausted.

## CAPTCHA and anti-automation

Anonymous traffic should trigger CAPTCHA or equivalent proof-of-human challenge when any of these are true:
- first anonymous scan if abuse pressure is high;
- repeated requests from the same IP/subnet;
- multiple unrelated domains in a short interval;
- known proxy/datacenter/automation reputation signal;
- failed quota/challenge attempts;
- global load-shedding mode.

Account/API traffic relies primarily on authenticated quotas, but suspicious automation may still be challenged or suspended.

CAPTCHA provider choice is a service-layer decision and must not add hidden research telemetry.

## Crawl and browser budgets

Every service job must persist its measurement-affecting budget configuration.

Initial hard ceilings for a single target:
- maximum discovered/fetched pages: **10**;
- maximum browser-escalated pages: **3**;
- maximum HTTP redirects per navigation chain: **10**;
- maximum outbound requests across browser activity: **500**;
- maximum downloaded response body per resource: **10 MiB**;
- maximum aggregate downloaded bytes per job: **100 MiB**;
- maximum browser wall time: **120 s**;
- maximum total job wall time: **300 s**;
- maximum worker memory: **1.5 GiB**;
- maximum worker CPU time: enforced by container/orchestrator where available.

These are service safety ceilings. If they differ from a canonical research protocol, the exact service measurement configuration must be recorded in provenance and the result must not be presented as equivalent to another protocol version.

Budget exhaustion is a terminal/partial measurement state, not a negative evidence label.

## SSRF and target validation

Only `http://` and `https://` URLs are accepted.

Reject before enqueue:
- credentials embedded in URL authority;
- non-HTTP schemes;
- malformed hostnames/IP literals;
- loopback;
- RFC1918/private IPv4;
- link-local;
- multicast;
- unspecified/reserved/bogon ranges;
- IPv6 loopback/link-local/unique-local/reserved ranges;
- cloud/provider metadata endpoints, including well-known metadata IPs/hostnames;
- localhost-style names and local search domains.

### DNS resolution

Before any connection:
1. resolve all A/AAAA records;
2. reject the target if **any** resolved address is prohibited;
3. record the validated address set and timestamp;
4. ensure the actual connection destination remains within a freshly validated public address set.

DNS answers must be revalidated after TTL expiry and before browser/network transitions where feasible.

### DNS rebinding

Do not rely on a one-time hostname check.

Mitigation requires both:
- application-level validation of each resolution;
- network-level egress policy that independently blocks private/link-local/metadata ranges.

A hostname resolving publicly during preflight but privately later must fail closed.

## Redirect validation

Every redirect target is treated as a new untrusted URL.

For each redirect:
- parse/canonicalize;
- enforce HTTP(S)-only scheme;
- resolve and validate destination addresses;
- apply private/metadata blocking again;
- enforce redirect-count budget;
- record the redirect chain.

A safe initial URL does not authorize following a redirect to an unsafe network destination.

## Browser isolation and egress

Browser workers must:
- run in an isolated container/VM/process boundary;
- run as non-root;
- keep the browser sandbox enabled;
- have no production/cloud credentials in environment or filesystem;
- use an ephemeral writable profile;
- use read-only application filesystem where practical;
- block access to host/container control sockets;
- deny private/link-local/metadata egress at network layer;
- restrict outbound traffic to required protocols/ports (normally DNS through controlled resolver and TCP 80/443);
- terminate the whole worker on budget expiry;
- discard the worker/profile after the job.

The service must never solve collection failures by disabling network isolation or broadening access to internal networks.

## Queue semantics

Service jobs have explicit states:

`submitted -> validated -> queued -> running -> completed|partial|failed|blocked|expired|cancelled`

Important distinctions:
- `blocked`: service safety/policy prevented execution;
- `failed`: execution attempted but did not complete;
- `partial`: bounded measurement produced incomplete evidence;
- `completed`: declared service workflow completed, not "site is compliant".

Workers use leases/visibility timeouts so a crashed worker can safely retry only where core idempotency permits.

Retry limits:
- infrastructure-transient retry: maximum **2** automatic retries;
- target-semantic failures (e.g. deterministic blocked/private target): **no retry**;
- rate-limited target: optional delayed retry within job budget.

## Artifact and result classification

### Class A — public/service summary
May include:
- target display hostname;
- observed evidence summary;
- explicit unknown/unavailable states;
- measurement/provenance versions;
- high-level failure categories.

### Class B — restricted acquired artifacts
Includes:
- HTML;
- screenshots;
- HAR/network captures;
- cookies/local storage;
- response headers/bodies;
- browser logs that may contain personal data/tokens.

Class B is never public by default.

### Class C — operational/security data
Includes:
- IP-derived rate-limit state;
- account/job identifiers;
- abuse events;
- infrastructure logs.

Class C is not research data by default.

## Retention defaults

Until #204/cost policy changes them:

### Anonymous
- service result summary: **24 hours**;
- Class B raw artifacts: delete within **1 hour after result generation**, unless temporarily retained for a declared failure investigation;
- security/rate-limit counters: rolling **7 days**, stored in minimized/pseudonymized form where possible.

### Authenticated pre-commercial pilot
- service result summary: **30 days**;
- Class B raw artifacts: **24 hours** by default;
- account-visible retention beyond that requires an explicit product/data-policy decision;
- security/audit records: **30 days** unless a concrete incident requires longer preservation.

ResearchRun/artifact retention for an approved study follows that study's separate protocol, not these service defaults.

## Failure and error UX

User-facing errors use stable codes and bounded explanations:

- `invalid_url`;
- `unsupported_scheme`;
- `blocked_target`;
- `rate_limited`;
- `quota_exceeded`;
- `target_timeout`;
- `target_unavailable`;
- `budget_exhausted`;
- `collection_partial`;
- `service_overloaded`;
- `internal_error`.

Do not expose:
- internal IP ranges;
- stack traces;
- worker filesystem paths;
- cloud metadata details;
- raw security-rule internals that materially aid bypass.

The result UI must distinguish "not observed" from "not present".

## Abuse logging

Record only what is needed to enforce security and investigate abuse:
- random request/job ID;
- timestamp;
- coarse client-network key or keyed hash where feasible;
- account ID where authenticated;
- target registrable domain;
- action/rule code;
- quota bucket and decision;
- coarse user-agent/client class only if needed.

Do not retain full request headers, arbitrary query strings or CAPTCHA payloads by default.

Security logs are operational data and follow the #206 research boundary.

## Domain ownership

The baseline service does not require domain ownership for a public-resource scan, because it measures publicly observable material.

Ownership proof **is required** before future features that:
- bypass normal public crawl limits;
- access authenticated/private resources;
- install verification files/tokens;
- schedule high-frequency monitoring;
- expose sensitive detailed artifacts to a requester.

No such feature is authorized by this specification.

## Bulk/API controls

Bulk/API mode is account-only.

Requirements:
- authenticated API token with scoped permissions;
- server-side quota enforcement independent of client;
- maximum targets/request and queue depth;
- idempotency key support;
- pagination/status endpoints rather than long-running HTTP requests;
- no arbitrary callback URL/webhook until outbound-callback SSRF policy exists;
- export only service-safe summaries unless stronger authorization applies.

## Threat/control matrix

| Threat | Primary controls | Failure mode |
| --- | --- | --- |
| SSRF/private-network access | URL parsing, DNS/IP validation, network egress deny rules | fail closed as `blocked_target` |
| DNS rebinding | repeated resolution validation + egress deny rules | terminate/blocked |
| unsafe redirect | validate every hop | terminate/blocked |
| browser escape/host access | sandbox, unprivileged ephemeral worker, no credentials/sockets | terminate worker |
| resource exhaustion | per-job budgets, concurrency caps, queue circuit breakers | partial/failed/overloaded |
| target DoS amplification | per-domain concurrency/cooldown + bounded requests | rate limited |
| service scraping/abuse | quotas, CAPTCHA, account/API limits | challenge/429 |
| sensitive artifact leakage | classification, restricted storage, short TTL, authorization | deny/delete |
| log privacy creep | minimal structured abuse events | retention expiry |
| semantic overclaim | explicit states and wording boundary | no compliance verdict |

## Unresolved decisions for implementation planning

The later implementation issue must still choose:
- CAPTCHA vendor/hosting model;
- account/identity provider;
- queue technology;
- container/orchestrator/network-policy implementation;
- controlled DNS resolver;
- storage backend/encryption;
- exact global concurrency from measured capacity;
- paid-plan limits from #204;
- incident-response/on-call ownership;
- jurisdiction/hosting/data-processing terms.

These are implementation/product decisions, not reasons to reopen the architecture discovery above.

## Acceptance check

- anonymous/account journeys defined: **yes**;
- CAPTCHA/anti-automation triggers defined: **yes**;
- IP/domain/account quotas and cooldowns defined: **yes**;
- asynchronous queue/status semantics defined: **yes**;
- crawl/browser hard ceilings defined: **yes**;
- SSRF/DNS-rebinding/redirect controls defined: **yes**;
- sandbox/egress requirements defined: **yes**;
- artifact privacy classes and TTL defaults defined: **yes**;
- failure UX defined: **yes**;
- abuse logging minimized and separated from research: **yes**;
- unresolved vendor/infrastructure decisions explicitly listed: **yes**.
