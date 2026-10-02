# Browser acquisition

Privacy Evidence uses an isolated Playwright worker only when the browser escalation policy requires rendered or behavioral evidence.

The worker:
- accepts HTTP/HTTPS URLs only;
- uses a fresh browser context;
- disables downloads;
- blocks service workers for the initial deterministic protocol;
- captures rendered HTML, cookies, local/session storage and network request metadata;
- closes its context/browser after each observation.

Playwright is currently pinned to 1.63.0 and is monitored as an npm dependency. The browser provider is replaceable through the PHP `BrowserProvider` interface.

Browser work must be constrained independently from HTTP workers. Network/private-address safeguards for browser navigation remain a required hardening item before untrusted production-scale execution.
