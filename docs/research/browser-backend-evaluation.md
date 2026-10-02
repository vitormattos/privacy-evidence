# Browser backend evaluation

Date: 2026-10-02
Issue: #52

## Mandatory capabilities

Privacy Evidence needs more than rendered DOM. Cookie/ePrivacy measurements require isolated browser state, cookie/localStorage/sessionStorage inspection, network request observation, deterministic scripted interaction and independently recyclable browser workers.

## Candidates

| Capability | Symfony Panther / WebDriver | chrome-php/chrome / CDP | Playwright worker |
| --- | --- | --- | --- |
| Rendered DOM / JavaScript | Yes | Yes | Yes |
| Browser isolation | Browser/client lifecycle; possible | New browser/page lifecycle; custom policy needed | First-class isolated BrowserContext |
| Cookies/storage | WebDriver/browser APIs, but advanced state handling is less central | Possible through CDP/custom code | First-class context cookies and storage-state APIs |
| Network observation/interception | Not a primary Panther abstraction; requires lower-level WebDriver/CDP work | Possible through CDP with custom event plumbing | First-class request events and routing |
| Interaction | Yes | Yes | Yes |
| Browser/version management | Driver + browser compatibility must be managed | Chrome executable compatibility managed by project | Playwright package/browser image versioning |
| PHP integration | Native | Native | Process/service boundary |
| Process isolation from PHP workers | Requires architecture around browser process | Requires architecture around browser process | Explicit worker boundary by design |
| Maintenance burden for research instrumentation | Medium/high | High | Low/medium |

## Current upstream evidence

- Symfony Panther is a current Symfony component for real-browser E2E testing and web scraping. It controls Chrome/Firefox through WebDriver and supports screenshots/JavaScript. See https://symfony.com/doc/current/testing/end_to_end.html.
- Playwright BrowserContext provides independent non-persistent contexts and cookie/storage state APIs. See https://playwright.dev/docs/api/class-browsercontext.
- Playwright exposes browser network request monitoring/routing directly. See https://playwright.dev/docs/network.
- chrome-php/chrome exposes Chromium through the Chrome DevTools Protocol and supports synchronous/asynchronous PHP control, but higher-level research instrumentation would remain project-owned. See https://github.com/chrome-php/chrome.

## Decision

Playwright remains the preferred initial backend.

Panther is attractive when the primary problem is PHP-native E2E interaction. Privacy Evidence additionally needs network/storage instrumentation and strong per-observation browser-state isolation. Implementing those requirements through WebDriver would add backend-specific plumbing.

Direct CDP through chrome-php/chrome provides lower-level control but transfers more protocol/session/event lifecycle code into this project. There is no evidence that this complexity provides a research benefit over Playwright for the initial scope.

The PHP `BrowserProvider` boundary remains backend-independent, so this decision is reversible.

## Reproducible benchmark

`browser/benchmark.mjs` exercises a deterministic dynamic fixture using fresh Playwright browser contexts. It records:

- browser and Node versions;
- browser startup time;
- total workload time;
- average context/page time;
- process maximum RSS;
- user/system CPU time;
- number of iterations;
- rendered output size.

Run from the browser environment:

```bash
cd browser
npm install
npx playwright install chromium
npm run benchmark
```

The benchmark deliberately uses `page.setContent()` instead of a live website so repeated measurements are not confounded by DNS, server latency or changing third-party content.

## Interpretation

The benchmark measures the chosen backend's resource envelope and provides data for #54 worker/concurrency sizing. It is not used to claim that Playwright is universally faster than Panther/CDP. The selection is driven first by mandatory instrumentation capability and isolation, then by measured resource cost of the selected backend.

## Re-evaluation triggers

Re-open this decision if:
- Playwright worker overhead becomes the dominant bottleneck even after adaptive escalation;
- a PHP-native backend provides equivalent network/storage/context isolation with lower measured operational cost;
- browser security/maintenance requirements materially change;
- the project needs a browser engine or platform not supported by the selected worker.


## Measured baseline

The 2026-10-02 CI benchmark on Chromium 153.0.8010.12 / Node v24.21.0 measured 10 isolated Playwright contexts at 54.54 ms average context time, 794.17 ms total and approximately 140 MiB maximum RSS. CPU use was 625,985 µs user and 90,276 µs system.

These results establish an initial resource envelope; they do not claim Playwright is faster than Panther or direct CDP. The backend decision remains capability-led and reversible through `BrowserProvider`.

See `docs/development/performance.md` for the complete benchmark baseline.
