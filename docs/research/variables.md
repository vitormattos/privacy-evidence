# Variables and measurement semantics

Schema version: **0.1.0-draft**

| Variable | Level | Type | Values / semantics |
|---|---|---|---|
| source_value | declared resource | string | immutable original value |
| normalized_url | resource | nullable URI | deterministic normalization result |
| resource_type | resource | nominal | institutional website, social network, video platform, link aggregator, third-party hosted page, malformed, unknown |
| acquisition_state | resource/document | nominal | success, unavailable, invalid, excluded, failure category |
| final_url | document | URI | URL after redirects |
| http_status | document | integer | HTTP status when obtained |
| tls_state | resource | nominal | verified / failure category / not attempted |
| acquisition_mode | document | nominal | http / browser |
| artifact_hash | document | SHA-256 | exact stored artifact identity |
| evidence_type | evidence | nominal | project evidence taxonomy |
| observation_state | evidence | nominal | present, absent, unknown, unavailable, invalid, excluded, not-applicable |
| confidence | evidence | [0,1] | detector confidence, not legal confidence |
| needs_review | evidence | boolean | whether human adjudication is required |
| profile_result | mapping | nominal | profile-specific mapped state with applicability |
| reviewer_type | review | nominal | human / AI-suggestion / adjudicator |
| protocol_version | run | semver-like | measurement protocol identity |
| schema_version | run/export | semver-like | persisted structure identity |

## Null and unknown

`null` means no value is represented for that field. It must not be overloaded to mean absent, unavailable and negative simultaneously. Domain state enums carry those meanings explicitly.

## Aggregation

Every percentage reports:
- numerator;
- eligible denominator;
- excluded/unavailable/unknown counts;
- unit of aggregation.

Multiple resources belonging to one entity cannot be collapsed without an explicit aggregation rule.
