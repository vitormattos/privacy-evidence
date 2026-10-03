# Hacktoberfest real-user use case

## The person

The project is being built for the researcher who created the original academic study that motivated Privacy Evidence. The researcher is also the project's real user for the Weekend Challenge.

No additional personal details are required for the challenge story.

## The original problem

The research started from a monograph at Seminário Simonton about digital ethics and its impact on Christian fellowship. One important part of that work was the relationship between data protection, privacy and the digital practices of churches.

For the empirical part, the researcher used the public directory of Igreja Presbiteriana do Brasil (IPB) congregations as the starting population. The directory included website addresses, but the raw list was not directly usable as a research sample.

The researcher had to manually work through cases such as:

- Facebook and Instagram pages rather than independent websites;
- link aggregators that required following another link to find the actual church website;
- registered domains that no longer resolved to a functioning site;
- sites that responded with errors or no usable content;
- valid websites that then had to be inspected one by one.

After reducing the source list to actual websites, the privacy/LGPD analysis itself still depended substantially on manual inspection.

That made the study difficult to reproduce. The procedure could be described, and parts of it were preserved in the old repository, but another researcher could not reliably rerun the same pipeline from the original source list and obtain a traceable set of observations using the same protocol.

## What the user needs

The user needs a tool that can receive a list of websites from an arbitrary source and turn it into a reproducible research workflow:

1. preserve the source population and provenance;
2. resolve and classify which entries are usable websites;
3. acquire public website evidence using a documented and repeatable protocol;
4. identify observable privacy signals such as privacy notices, cookie controls, controller identity, DPO/privacy contact, rights information and related disclosures;
5. preserve the exact evidence, detector/model version and processing provenance;
6. route uncertain or conflicting classifications to human review;
7. evaluate the same generic evidence against versioned regulatory profiles such as LGPD or GDPR without recrawling merely because the legal profile changes;
8. export enough provenance to rerun the analysis later and explain why results differ when websites themselves have changed.

The immediate historical use case is a population of IPB websites. The same workflow should also support another denomination, a company with many public sites, or any research population supplied as a list of URLs.

## Why PHP-native ML matters

Transparent deterministic rules remain useful and auditable, but real privacy language varies. A fixed keyword/rule detector can miss a disclosure that expresses the same concept using different wording.

The PHP-native ML path is therefore used as a second opinion over the same observable evidence. It can identify a candidate signal that the deterministic rule missed, expose its probability and exact model provenance, and send disagreement to the existing auditable review queue.

It does **not** replace human ground truth and does **not** make a legal-compliance verdict.

Keeping this path local and PHP-native matters to the user because the research workflow can run from a reproducible project checkout without requiring Python, a remote AI provider, API credentials or an external inference service.

## Minimal acceptance scenario

The challenge demo uses one controller-identity sentence whose meaning is relevant to the research workflow.

Success means that:

1. the deterministic rule and the local Rubix model both run through project code;
2. the rule misses the controller-identity signal while the ML model identifies it as a candidate;
3. the output includes the model probability, threshold, artifact SHA-256 and training provenance;
4. the rule/ML disagreement is recorded as an `ai_suggestion`;
5. the case remains pending for human review;
6. removing the model artifact makes the ML demo fail rather than silently falling back, showing that ML is materially part of the demonstrated result.

This scenario is implemented and tested by `examples/hacktoberfest/run-demo.sh` and the corresponding integration tests.

## User feedback available before submission

The real user has confirmed the underlying research problem and the reason the tool is being built: the original website analysis involved substantial manual filtering and inspection, which weakened reproducibility for empirical software-engineering research.

No separate post-demo reaction or usability claim is recorded yet. The submission must not invent one.
