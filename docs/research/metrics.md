# Derived metrics and denominator semantics

## General rule

A percentage is defined by a numerator, an eligible denominator and explicitly reported excluded/unknown/unavailable counts.

## Resource availability

Numerator: resources reaching a protocol-defined observable terminal success.

Denominator: all declared resources eligible for HTTP/HTTPS processing after source import.

Report invalid, excluded and unavailable separately.

## Evidence prevalence

For evidence type E:

Numerator: eligible resources/entities with reviewed or protocol-accepted state `present`.

Denominator: eligible observations for which E was actually observable under the protocol.

Unknown/unavailable/not-applicable are reported separately and are not silently negatives.

## Detector performance

Per signal:
- TP, FP, TN, FN;
- precision = TP / (TP + FP);
- recall = TP / (TP + FN);
- F1 = harmonic mean of precision/recall;
- support;
- abstention/unknown count and coverage where applicable.

Accuracy is never the only metric for imbalanced classes.

## Aggregation level

Every metric declares one of:
- document;
- resource;
- entity.

Multiple resources per entity require an explicit rule (for example any-present, primary-resource-only, or separate resource-level reporting).

## Historical compatibility

Legacy 2025 metrics are not silently redefined. Any comparison states the mapping and whether denominators are semantically equivalent.
