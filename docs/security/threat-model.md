# Crawler and browser threat model

## Trust boundaries

Untrusted:
- imported URLs;
- DNS answers;
- redirects;
- HTTP headers/bodies;
- HTML/JavaScript;
- downloads;
- third-party browser resources.

Trusted:
- project code/configuration;
- controlled fixtures;
- explicitly provisioned infrastructure credentials.

## Assets

- host/network isolation;
- credentials/environment variables;
- research data;
- filesystem;
- CPU/memory/disk;
- run integrity and provenance.

## Required controls

### SSRF
- allow only HTTP/HTTPS;
- resolve hostname before connection;
- reject loopback, private, link-local, reserved and cloud-metadata destinations;
- revalidate every redirect target;
- defend against DNS rebinding by binding validation to actual connection resolution where transport permits.

### TLS
TLS verification is on by default. Certificate/hostname failures are observations, not reasons to disable verification.

### Resource exhaustion
Bound:
- response body size;
- redirects;
- connection/read timeout;
- crawl pages/depth/bytes/time;
- queue size;
- browser workers;
- browser job lifetime.

### Browser
- isolated disposable profiles/contexts;
- no inherited user credentials;
- downloads disabled or quarantined;
- no host filesystem mounts beyond required artifact storage;
- reset cookies/storage between independent conditions;
- terminate/recycle workers.

### Data
Raw HTML/screenshots/cookies/storage are research artifacts subject to #57. They are not committed by default.

## Verification

Integration tests must include private-IP/loopback rejection, unsafe schemes, redirect-to-private target and oversized/resource-limit behavior.
