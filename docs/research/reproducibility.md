# Reproducibility and replication

## Terminology

**Reproduction** attempts to regenerate deterministic outputs from the same declared inputs, code/protocol/configuration and preserved artifacts.

**Replication** repeats the measurement on newly acquired live-web observations using the same declared method.

Live web content is temporally unstable; a replication is not expected to produce byte-identical website artifacts.

## ResearchRun minimum provenance

A run records:
- run id and start time;
- Git commit;
- dataset hash/version;
- protocol version;
- schema version;
- detector versions;
- regulatory-profile versions;
- annotation-handbook version;
- acquisition/crawl configuration;
- browser/runtime version when used;
- environment/runtime information.

## Replication package

Publishable packages should contain:
- normalized input manifest;
- run manifest;
- non-restricted derived observations/evidence;
- review decisions allowed by the data policy;
- metric outputs;
- report;
- hashes for referenced artifacts;
- exact commands/configuration.

Raw third-party HTML, screenshots, cookies and browser storage may be restricted by data-minimization, copyright or privacy rules and can be represented by hashes/metadata instead.

## Determinism

Pure normalization, classification, detector and metric logic must be deterministic for identical inputs/configuration.

Network acquisition and browser rendering are externally variable; their exact acquired artifact is therefore hashed and preserved/referenced as evidence.

## Clean-room acceptance

The PoC acceptance process uses a clean checkout/environment and distinguishes exact reproduction of deterministic artifacts from temporal replication of live observations.
