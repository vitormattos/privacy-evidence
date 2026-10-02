# Release and supply-chain policy

## Release checklist

Before publishing a release:

1. all deterministic CI gates are green;
2. Composer audit and REUSE lint pass;
3. software, protocol, schema, detector and regulatory-profile versions are recorded;
4. CHANGELOG and CITATION metadata are updated;
5. an SPDX JSON SBOM is generated;
6. release notes describe research-semantic impact;
7. the tagged commit and declared inputs are sufficient to rebuild the release;
8. milestone research releases are archived according to the DOI policy in `docs/research/citation.md`.

## SBOM

Release automation generates an SPDX JSON SBOM with Anchore Syft via `anchore/sbom-action`. The SBOM is attached as a workflow/release artifact.

## Permissions

Normal CI is read-only. Release workflow grants only the permissions required to attach release assets.

## Provenance

A research release identifies:
- Git tag/commit;
- software version;
- protocol version;
- schema version;
- detector/profile versions;
- lockfiles;
- container/browser versions where applicable.

Signing/attestation may be added later through a documented ADR; it is not required for ordinary development commits.
