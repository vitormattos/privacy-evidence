# ML-assisted review prioritization

PHP-native ML is a second opinion over immutable detector output. It does not rewrite evidence and it never creates a human review.

For a rule evidence item and a model prediction of the same signal, the review policy records:

- the original rule state and detector/version;
- the ML candidate state, positive probability and threshold;
- the exact model artifact SHA-256 and model provenance;
- whether rule and ML disagree;
- whether the probability is within the configured uncertainty margin;
- a deterministic review-priority score.

The default uncertainty margin is 0.15 around the prediction threshold. Priority is additive and intentionally simple:

- +100 for rule/ML disagreement;
- +50 for low-confidence/near-threshold prediction;
- +25 when the deterministic rule already requested review or produced a non-binary state.

The numeric score orders review work only; it has no legal meaning and is not a confidence calibration.

AI suggestions are stored with `reviewerType=ai_suggestion` and reviewer id `ai:php-native-ml`. Recording an AI suggestion does **not** mark a queue item reviewed. Only human/adjudicator decisions complete a review item, so AI records are mechanically excludable from human-ground-truth calculations.

Fixed rule/model inputs produce the same assessment and priority.
