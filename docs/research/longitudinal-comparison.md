# Longitudinal run comparison

Privacy Evidence compares runs using the stable imported **resource ID** as the primary longitudinal identity key. A URL or name change for the same resource ID is reported as a resource change rather than silently creating a new entity.

Evidence transitions are compared as sorted sets of observed states per `resourceId + evidenceType`. The comparison intentionally does not collapse `unknown`, `unavailable`, `not_applicable` or other semantic states into `absent`.

A comparison is marked non-comparable when the research protocol or schema version differs. The tool still reports structural differences, but consumers must treat metric interpretation across those runs as methodologically qualified.

Runs themselves are immutable historical records. Longitudinal comparison reads persisted run/resource/evidence state and never rewrites either run.
