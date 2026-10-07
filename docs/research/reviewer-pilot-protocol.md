# Reviewer-instrument human pilot protocol

Pilot protocol version: **0.1.0**

Governing issue: #195

Status: **prepared; human execution required**.

## Purpose

This pilot validates whether an actual human can use the frozen review instrument without undocumented help, live-Web evidence or knowledge of detector output.

It evaluates **reviewer usability and state semantics**, not detector accuracy and not inter-rater agreement.

AI agents may prepare the packet, validate its technical integrity and summarize feedback. They must not invent the human observations required by this protocol.

## Pilot population

Use the current PoC ResearchRun:

`01a10d65-a2ec-7279-b51f-f16990e95313`

Workflow provenance:

`37358645153`

This PoC run is intentionally separate from the full IPB ResearchRun currently used for the publication-oriented RQ1/RQ2 analyses:

`01a10a29-cf8d-7711-8e6d-ec954e592cc2`

The pilot therefore does not consume final full-IPB evaluation labels.

## Packet generation

Restore the preserved PoC run state and original SHA-256 artifacts, then generate a deterministic pilot package:

```bash
php bin/privacy-evidence review:sample \
  01a10d65-a2ec-7279-b51f-f16990e95313 \
  data/review/pilot-195-v1.json \
  --per-stratum=1 \
  --seed=privacy-evidence-reviewer-pilot-v1

php bin/privacy-evidence review:html \
  data/review/pilot-195-v1.json \
  data/review/pilot-195-v1.html \
  --context-dir=data/exports/01a10d65-a2ec-7279-b51f-f16990e95313 \
  --artifacts-dir=data/raw/artifacts
```

Record:
- package JSON SHA-256;
- HTML SHA-256;
- package/schema version;
- annotation-handbook version;
- reviewer UI source commit;
- case/reviewable/deferred counts.

Do not use `--test-mode` for the human pilot.

## Human participant

Minimum requirement: at least one actual human who did not implement the reviewer UI.

A second pilot participant is useful if available but is not a substitute for the two independent final reviewers required later by #33/#84.

Use a privacy-preserving stable participant identifier such as `pilot-human-01`. Do not record unnecessary personal data.

## Pilot task

The participant receives:
- the self-contained offline HTML;
- the same instructions intended for final reviewers;
- no detector state/confidence;
- no live-site links as evidence;
- no assistance from another reviewer while making pilot decisions.

Ask the participant to complete a **small usability slice**, not the entire package. Stop after either:
- 10 reviewable cases; or
- 20 minutes of active review;

whichever occurs first.

The pilot facilitator may answer only questions about operating the interface. Every clarification the participant needs must be recorded because undocumented explanation is itself a usability finding.

After the review slice, show at least one deferred case and ask the participant to explain what the deferred state means and what action they believe is expected.

## Required feedback

Record the participant's response to each item without rewriting it into a more favorable interpretation.

### State meaning

Can the participant distinguish:
- `present`;
- visible label “not observed in preserved material” (stored as `absent`);
- `unknown`;
- `unavailable`;
- deferred/investigation cases?

For each confusion, record the exact UI text or instruction that caused it.

### Evidence boundary

Ask:
- Did any case seem to require opening the live website?
- Was it clear that only preserved material may support the primary review decision?
- Was the collected-page URL understood as provenance rather than a link to new evidence?

### Instructions

Ask:
- Which instruction was unclear or missing?
- Did the participant need facilitator help to continue?
- Was rationale entry understandable?
- Was export behavior understandable?

### Workload

Record:
- number of completed reviewable cases;
- active review duration;
- median time per completed case when available from the UI;
- whether the participant reported fatigue or repetitive burden;
- any keyboard/navigation issue.

### State confidence

Ask the participant to rate, on a simple 1–5 usability scale, confidence that they understood the **meaning of the labels**. This score is a usability aid only; it is not a scientific measurement of detector validity.

## Pilot result record

Create:

`docs/research/results/reviewer-pilot-195-v1.md`

The result record must include:
- participant id/type = human;
- date/time;
- packet hashes and versions;
- number of cases attempted/completed;
- raw feedback summary;
- facilitator clarifications required;
- observed ambiguities;
- proposed instrument/handbook changes;
- whether another pilot iteration is required.

Do not publish the participant's case-level human labels as a gold dataset.

## Change/freeze rule

If the pilot reveals a material ambiguity:
1. change the UI/handbook;
2. increment the affected instrument/handbook version;
3. rerun automated browser tests;
4. run another human pilot slice when the change affects decision semantics or instructions.

If no material ambiguity remains, freeze:
- reviewer UI commit/version;
- annotation-handbook version;
- annotation-package schema version;
- pilot packet hashes;
- final reviewer handoff instructions.

Only then may #195 close and unblock #178/#196.

## Acceptance checklist

#195 is complete only when all are true:

- [ ] an actual human completed the pilot slice;
- [ ] packet/UI/handbook/schema hashes or versions are recorded;
- [ ] feedback on ambiguity, instructions, workload and state meanings is preserved;
- [ ] live-Web evidence was not required for the primary task;
- [ ] facilitator clarifications are recorded;
- [ ] any required UI/handbook changes are completed and versioned;
- [ ] browser regression tests pass on the frozen version;
- [ ] the result record exists;
- [ ] final reviewer UI/handbook versions are explicitly frozen.

Implementation tests alone cannot check the first three human-observation requirements.
