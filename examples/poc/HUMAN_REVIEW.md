# Human review handoff for the PoC

The PoC workflow exports two **identical reviewer-neutral packets** before any review decision is imported:

- `review/reviewer-a.jsonl`
- `review/reviewer-b.jsonl`

Give each file to a different human reviewer. Reviewers must work independently and use `docs/research/annotation-handbook.md`.

For each JSON line, edit only the `decision` object:

- keep `type` unchanged unless the packet is malformed;
- set `state` to one of the allowed ObservationState values;
- keep `reviewerType` as `human`;
- use a stable privacy-preserving reviewer id such as `human:reviewer-a`;
- set an ISO-8601 `reviewedAt`;
- write a concise evidence-based `rationale`.

Do not share completed files between reviewers before both independent passes are finished.

After both are complete:

```bash
php bin/privacy-evidence review:import reviewer-a.jsonl
php bin/privacy-evidence review:import reviewer-b.jsonl
php bin/privacy-evidence review:agreement RUN_ID human:reviewer-a human:reviewer-b
```

Agreement is calculated before adjudication. AI suggestions may assist navigation but do not count as human reviewers.
