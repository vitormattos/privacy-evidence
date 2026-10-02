# Security Policy

## Reporting a vulnerability

Do not open a public issue for a vulnerability that could expose users, research infrastructure or collected data before remediation.

Use GitHub's private vulnerability reporting/security advisory mechanism when enabled for this repository. If that mechanism is unavailable, contact the repository owner privately through the contact information on the owner's GitHub profile.

## Security scope

Security-sensitive areas include, but are not limited to:

- SSRF and access to private/internal networks;
- redirect or DNS-rebinding bypasses;
- unsafe URL schemes;
- TLS verification failures;
- command or shell injection;
- unsafe browser automation or sandboxing;
- malicious JavaScript execution;
- local-file or credential exposure;
- oversized/decompression/resource-exhaustion attacks;
- unbounded crawling or queue growth;
- accidental publication of research data or personal data;
- dependency and CI supply-chain vulnerabilities.

## Design expectations

Public URLs, HTML, headers and JavaScript are untrusted input.

Normal collection must not access loopback, private, link-local, reserved or cloud-metadata endpoints. Browser workers should be isolated and should not inherit unrelated credentials or sensitive host access.

Security failures are observations or job failures; they must not be bypassed silently to increase collection success.

## Supported versions

The project is pre-1.0 and under active development. Security fixes are expected to target the current default branch until a formal supported-release policy is established.
