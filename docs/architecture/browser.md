# Browser acquisition and measurement protocol

Protocol component version: **1.0.0**

Privacy Evidence uses an isolated Playwright worker only when the browser escalation policy requires rendered or behavioral evidence. Static HTTP acquisition remains the default.

## Controlled initial state

Every browser observation:
- launches a fresh Chromium process and browser context;
- starts with no cookies, localStorage or sessionStorage from previous observations;
- accepts only HTTP/HTTPS navigation;
- disables downloads;
- blocks service workers for the initial protocol;
- does not inherit application credentials from the PHP process;
- closes the context and browser in a `finally` block.

Independent experimental conditions (for example no interaction, reject and accept) must be executed as separate observations so state from one condition cannot contaminate another.

## Network safety

Before top-level navigation and every intercepted request, the worker resolves the destination and rejects:
- localhost;
- loopback;
- private IPv4 ranges;
- link-local ranges;
- carrier-grade NAT;
- multicast/reserved IPv4 ranges;
- IPv6 loopback, unspecified, link-local, unique-local and multicast ranges.

Redirect/subresource requests are revalidated through route interception. A blocked request is aborted rather than retried with weakened safeguards.

## Navigation and readiness

The initial readiness condition is Playwright `domcontentloaded`, bounded by the job timeout. Additional deterministic waits may be supplied as explicit actions when an experiment requires them.

The worker returns the final browser URL after navigation, not merely the requested URL.

## Auditable actions

The initial action vocabulary is deliberately small:
- `click` with an explicit selector;
- `wait` with a bounded number of milliseconds.

The caller must preserve the action sequence as part of experiment/run configuration when actions affect measured behavior. Future actions require a protocol/version change if they can alter observations.

## Captured observations

The browser worker emits:
- rendered HTML;
- final URL;
- capture timestamp;
- Chromium version;
- cookies visible to the browser context;
- localStorage;
- sessionStorage;
- request URL, HTTP method and resource type.

These values are evidence inputs. Their presence does not itself imply a regulatory conclusion.

## Failure semantics

Process timeout, navigation failure, blocked private/reserved destinations and invalid worker output are explicit browser-job failures. They must be recorded by orchestration/telemetry rather than converted to absent privacy evidence.

## Backend boundary

Playwright is pinned by `browser/package.json` and monitored as an npm dependency. PHP depends only on the replaceable `BrowserProvider` interface. ADR 0004 records the initial backend decision.

Browser work has an independent resource/concurrency budget from lightweight HTTP work.
