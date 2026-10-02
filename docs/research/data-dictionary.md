# Research data dictionary

Data dictionary version: **0.1.0-draft**

| Field | Type | Unit | Nullable | Meaning / source |
|---|---|---|---|---|
| id | string | resource/run/evidence | no | stable identifier within its schema |
| sourceValue | string | declared resource | no | immutable value supplied by the source dataset |
| normalizedUrl | URI | resource | yes | deterministic HTTP/HTTPS normalization; null when not representable |
| type | enum | resource/evidence | no | versioned taxonomy value |
| metadata | object | resource | no | source-specific scalar metadata isolated from generic analyzers |
| startedAt | date-time | run | no | UTC run start |
| gitCommit | string | run | no | code revision used by the run |
| datasetHash | SHA-256 | run | no | identity of the input/source snapshot |
| protocolVersion | string | run | no | measurement protocol identity |
| versions | map | run | no | schema/detector/profile/handbook/browser-policy versions |
| configuration | object | run | no | measurement-affecting run configuration |
| artifactHash | SHA-256 | document/evidence | no | identity of exact acquired supporting artifact |
| state | enum | evidence | no | present/absent/unknown/unavailable/invalid/excluded/not-applicable |
| confidence | number [0,1] | automated evidence | no | classifier confidence, never legal confidence |
| needsReview | boolean | evidence | no | whether the protocol requires manual adjudication |
| reviewerType | enum | review | no | human, ai_suggestion, adjudicator |
| rationale | string | review | no | auditable basis for the review decision |

## Null semantics

Null means that the field does not contain a represented value. Semantic states such as unavailable/unknown/not-applicable are encoded explicitly and must not be collapsed into null.

## Schema evolution

Exports identify their schema version. A semantic field change requires a schema-version bump; migrations cannot silently reinterpret historical data.
