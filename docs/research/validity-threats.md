# Threats to validity

## Construct validity

**Threat:** public website evidence is an incomplete proxy for organizational privacy practice.

**Mitigation:** define the construct as publicly observable evidence; prohibit compliance-certification claims; retain generic evidence independently from regulatory mapping.

## Sampling / external validity

**Threat:** source datasets may omit organizations/resources or overrepresent particular sectors.

**Mitigation:** preserve sampling frame, source snapshot, filtering steps and exclusions; report population/sample scope.

## Temporal validity

**Threat:** live websites change during and between runs.

**Mitigation:** timestamp and hash acquired artifacts; version run configuration; distinguish reproduction from replication.

## Measurement reliability

**Threat:** keyword/rule detectors can generate false positives/negatives.

**Mitigation:** reviewed gold dataset, per-signal precision/recall/F1, error analysis, uncertainty/abstention and human review.

## Browser effects

**Threat:** geolocation, language, cookies, prior browser state and consent interactions alter rendered behavior.

**Mitigation:** clean browser profiles, recorded runtime versions, controlled interaction sequences and independent experimental conditions.

## Network failures

**Threat:** timeouts/TLS/DNS failures may be misclassified as absence.

**Mitigation:** failure taxonomy and explicit unavailable state; never count failed acquisition as negative evidence.

## Researcher/reviewer bias

**Threat:** ambiguous evidence can be interpreted inconsistently.

**Mitigation:** versioned annotation handbook, independent human subset, agreement measurement and post-agreement adjudication.

## Conclusion validity

**Threat:** percentages can be misleading with unstable denominators or class imbalance.

**Mitigation:** formal numerator/denominator definitions, support counts, missing-data reporting and class-specific performance metrics.
