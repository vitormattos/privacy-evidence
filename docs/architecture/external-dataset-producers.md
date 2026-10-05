# External dataset producer boundary

Privacy Evidence is intentionally **source-agnostic**.

The core application does not know how to discover websites from the IPB directory, a corporate CMDB, a government API, a spreadsheet service, another denomination, or any other organization-specific source.

Instead, the boundary is:

```text
arbitrary external source
        ↓
external producer / ETL / script / notebook / API client
        ↓
canonical CSV or JSON dataset
        ↓
Privacy Evidence core
        ↓
normalization / classification / crawl / evidence / review / profiles / reports
```

## Why this boundary exists

Source discovery is open-ended. If each upstream system became a first-class provider inside the package, the project would accumulate organization-specific code and users around the world would need to extend the core simply to prepare their own URL population.

That is not the responsibility of the measurement engine.

The core therefore owns only the **canonical dataset contract**. A producer can be:

- a shell script;
- a PHP/Python/R script;
- an ETL job;
- a notebook;
- a database export;
- another repository/package;
- an API client;
- a manually curated file, when that is the declared research method.

No producer registration is required inside Privacy Evidence.

## Canonical input

The built-in formats are CSV and JSON. Each record must provide:

- `id`: stable identifier inside the declared population;
- `name`: human-readable label;
- `url`: original source URL value.

Additional scalar fields are preserved as resource metadata.

Privacy Evidence then owns URL normalization and generic resource classification so the same rules are applied independently of where the population came from.

## Optional provenance sidecar

An external producer may place a sidecar next to the dataset:

```text
population.csv
population.csv.provenance.json
```

The generic sidecar requires:

```json
{
  "schemaVersion": "1.0.0",
  "producer": "example-producer",
  "producerVersion": "1.2.3",
  "dataset": {
    "sha256": "<sha256 of population.csv>"
  }
}
```

Other source-specific fields are allowed.

When present, Privacy Evidence verifies that `dataset.sha256` matches the canonical input before a run and records the producer/sidecar identity in run provenance. A changed dataset with a stale sidecar is rejected.

The sidecar is intentionally generic: the core does not interpret organization-specific fields.

## Reproduction vs replication

A producer should preserve enough upstream material to reproduce the canonical dataset when licensing/privacy rules allow it.

For live sources:

- **reproduction** reruns deterministic extraction from the same preserved upstream snapshot;
- **replication** contacts the live upstream source again and creates a new temporal population.

The canonical dataset hash is the measurement-engine input identity. The producer sidecar links that input back to whatever upstream snapshot/process generated it.

## Example: IPB

The Hacktoberfest example under `examples/hacktoberfest/ipb/` is deliberately **not** part of `src/`.

Its standalone script knows how to contact and parse the current IPB/iCalvinus directory and emits an ordinary canonical CSV plus provenance. From that point onward, the normal Privacy Evidence CLI is used.

That example demonstrates one possible producer. It does not establish an IPB plugin/provider API in the package.
