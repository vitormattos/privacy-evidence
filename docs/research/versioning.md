# Multidimensional research versioning

Privacy Evidence versions measurement semantics independently from software releases.

## Version dimensions

- software release;
- research protocol;
- schema;
- detector;
- regulatory profile;
- annotation handbook;
- crawl/browser policy where behavior changes observations.

## Change rules

A version must change when a modification can change the meaning or classification of an observation produced from the same acquired artifact.

Examples:
- new detector synonym that changes matches → detector version;
- changing unknown to absent semantics → protocol/schema version;
- new GDPR applicability rule → GDPR profile version;
- typo/documentation change with no semantic effect → no research-semantic bump.

## Historical comparability

Longitudinal comparisons must state whether compared runs use equivalent measurement definitions. Incompatible definitions are not force-mapped.
