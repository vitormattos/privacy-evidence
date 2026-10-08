# Reviewer pilot handoff kit

Governing issue: #195

Status: **human execution still required**.

This document reduces facilitator ambiguity for the reviewer-instrument pilot defined in `reviewer-pilot-protocol.md`. It does not replace the required human participant.

## Participant eligibility

Use at least one actual human who:
- did not implement the reviewer UI;
- has not seen detector output for the pilot cases;
- can complete the task independently;
- is willing to provide usability feedback.

Domain expertise is useful but not required for the first usability pilot. Record only a coarse participant type (for example, `pilot-human-01 / technical reviewer`) unless more personal information is genuinely needed.

## Neutral invitation text

> We are testing the usability of an offline review interface used in a research project. The task is to classify evidence using only the preserved material shown in the interface. We are testing the interface and instructions, not you. Please do not open the live website or search for additional evidence. Stop after 10 reviewable cases or 20 minutes, whichever comes first. Afterward, I will ask a few questions about unclear labels, instructions and workload.

Do not mention detector predictions, expected answers or the project's desired findings.

## Material to hand over

Provide:
- `pilot-195-v1.html` generated without `--test-mode`;
- the concise reviewer instructions embedded in the page;
- no detector state/confidence;
- no separate live-site links;
- no completed example using a real pilot case.

Before handoff, record:
- JSON SHA-256;
- HTML SHA-256;
- reviewer UI Git commit;
- package/schema version;
- annotation-handbook version;
- case/reviewable/deferred counts.

## Facilitator opening script

Tell the participant:

1. Use only the preserved evidence shown in the offline page.
2. `present` means the requested evidence is observable in the supplied material.
3. “Not observed in preserved material” is stored as `absent` and is limited to the supplied material; it is not an organization-wide conclusion.
4. Use `unknown` when the supplied material is insufficient or ambiguous.
5. `unavailable` and deferred states represent measurement/review limitations, not negative evidence.
6. Write a short rationale using only what is shown.
7. Do not ask another reviewer for a decision.
8. Stop after 10 reviewable cases or 20 active minutes.

Do not explain how the detector works.

## What the facilitator may answer

Allowed:
- how to navigate;
- how to save/export;
- where a field/button is;
- how the stated interface labels are intended to be read if the participant asks.

Every clarification required to continue must be logged verbatim or near-verbatim.

Not allowed:
- whether a case “should” be present/absent;
- hints about detector output;
- opening/searching the live site;
- supplying external context not preserved in the packet;
- resolving substantive ambiguity for the participant.

If a substantive clarification is required, that is a pilot finding.

## Pilot observation sheet

Create a local/private note with:

```text
participant_id:
participant_type:
started_at:
finished_at:
active_minutes:
reviewable_cases_attempted:
reviewable_cases_completed:
deferred_case_shown: yes/no
label-confidence-1-to-5:

clarifications_required:
- ...

state_confusions:
- label:
  participant_comment:
  interface_text_involved:

evidence_boundary_feedback:
- wanted_live_site: yes/no
- preserved_material_rule_clear: yes/no
- provenance_url_understood: yes/no
- comments:

instruction_feedback:
- unclear_or_missing:
- rationale_field_clear: yes/no
- export_clear: yes/no

workload_feedback:
- fatigue_or_repetition:
- navigation_or_keyboard_issue:
- other:

proposed_changes:
- ...
```

This note is facilitator working material. The repository result record should contain only the minimized information required by the protocol.

## Post-task questions

Ask without leading the participant:

1. In your own words, what is the difference between “present”, “not observed in preserved material”, “unknown” and “unavailable”?
2. What does a deferred case mean to you?
3. Did any case make you feel that you needed to open the live website? Why?
4. Was it clear that the URL shown is provenance rather than permission to gather new evidence?
5. Which instruction or label was most confusing?
6. Did you need help to continue? What help?
7. Was the rationale field clear?
8. Was saving/exporting clear?
9. How tiring or repetitive did the task feel?
10. On a 1–5 scale, how confident are you that you understood the label meanings?

Preserve negative/critical feedback. Do not rewrite it into favorable wording.

## Result record template

Create `docs/research/results/reviewer-pilot-195-v1.md` after the human pilot:

```markdown
# Reviewer pilot #195 — v1

## Provenance
- participant id/type:
- date:
- pilot JSON SHA-256:
- pilot HTML SHA-256:
- reviewer UI commit:
- annotation handbook version:
- package/schema version:
- reviewable/deferred counts:

## Execution
- active duration:
- reviewable attempted/completed:
- deferred case inspected:
- facilitator clarifications:

## Feedback
### State meanings
...

### Evidence boundary
...

### Instructions/export
...

### Workload/navigation
...

### Label-confidence score
...

## Instrument changes
- required:
- implemented in:
- version changes:

## Pilot decision
- another human pilot required: yes/no
- rationale:

## Freeze decision
Only if no material ambiguity remains:
- frozen UI commit/version:
- frozen handbook version:
- frozen schema/package version:
- frozen packet hashes:
```

Do not publish case-level pilot labels as the gold/reference dataset.

## Decision rule

A second pilot iteration is required when:
- a label/state meaning is materially misunderstood;
- the participant needs undocumented substantive guidance;
- live-site evidence appears necessary because the preserved packet is insufficient;
- a UI/instruction change affects decision semantics.

Minor cosmetic/navigation issues may be fixed without another pilot only when they cannot affect the meaning or evidence conditions; record that decision.

## Human gate reminder

This kit can be generated, checked and improved automatically. #195 still cannot close until an actual eligible human completes the task and the result/freeze record exists.
