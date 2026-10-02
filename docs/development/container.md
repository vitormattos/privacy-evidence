# Reproducible container environment

The project keeps PHP and browser workloads in separate containers because they have different resource and security profiles.

## PHP application

Build:

```bash
docker compose build app
```

Preflight:

```bash
docker compose run --rm app doctor
```

The application image uses PHP 8.4, UTC and C.UTF-8. Research data is mounted from `./data`; it is not copied into the image.

## Browser worker

The browser worker is based on the Playwright image matching `browser/package.json` (currently Playwright 1.63.0). It runs as the non-root `pwuser`, with a read-only filesystem, dropped Linux capabilities and a bounded temporary filesystem.

Build:

```bash
docker compose build browser
```

The orchestration layer should record the exact browser version returned by the worker in ResearchRun evidence metadata.

## Reproducibility

Container tags, PHP version, Composer lock hash, browser package version and Git commit are all measurement-environment inputs. ResearchRun metadata records runtime/component versions; tagged research releases should additionally record image digests where available.

The container setup contains no credentials or research datasets. Secrets, if later required for optional integrations, must be injected at runtime and never committed.
