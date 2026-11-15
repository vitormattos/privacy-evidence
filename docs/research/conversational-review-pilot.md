# Conversational pilot H0: rationale, reproducibility and reporting

Instrument prototype version: **0.2.0** (2026-11-15). Status: **technically prepared, not independently human-piloted, not frozen for final evaluation**.

Governing issue: [#195](https://github.com/vitormattos/privacy-evidence/issues/195). The approved archived-packet pilot, participant feedback, instrument freeze and downstream #178/#196 remain **pending**. The repository's existing [pilot protocol](reviewer-pilot-protocol.md) and [handoff](reviewer-pilot-handoff.md) are the normative requirements.

## Problem and research motivation

The project author reported that the earlier offline HTML review form was confusing and labor-intensive even for its developer (qualitative **developer-reported** concern, not independent participant evidence). The long handbook, navigation and manual file handling create a plausible *instructional burden* for non-implementers. A one-paste conversational facilitator is a candidate instrument to reduce setup actions and prevent the participant from manually locating documentation or manipulating JSON.

This is a **design hypothesis**, not a measured benefit. Conversational facilitation could instead increase per-case reading, dialogue turns, model errors, participant fatigue, response anchoring or misunderstanding of evidence sufficiency. Therefore the project preserves the HTML reviewer as the established technical baseline and separately evaluates the H0 proposal before substituting it. No result in this document establishes improved usability or detector validity.

## Minimal entry for a participant

- **Short entry prompt:** [start-h0-v0.2.md](prompts/start-h0-v0.2.md). It asks the selected AI to load the versioned repository prompt, with an explicit failure/fallback when web access is not available.
- **One-paste, provider-neutral offline fallback:** [human-one-paste-v0.2.txt](prompts/human-one-paste-v0.2.txt). A person copies it into a fresh AI conversation and answers the questions; it embeds the complete **public synthetic fixture**.
- **Source script:** [human-conversational-protocol-v0.2.md](prompts/human-conversational-protocol-v0.2.md).
- **Source cases:** [conversational-review-v0.1.json](../../tests/Fixtures/research/conversational-review-v0.1.json): 11 distinct fictitious cases, **10 reviewable + 1 deferred**.

The short prompt contains the actual repository and issue links. The fallback requires no browsing by the model, no separate attachments and no additional handbook file. **Only the prompt is long; the participant-facing invitation is short.** All data in the distributed fallback are fictitious. A model that cannot retrieve the short prompt's URL must not pretend to have done so.

Reproduce/verifiably check the distributed file:

```bash
node scripts/research/build-conversational-pilot.mjs
node scripts/research/build-conversational-pilot.mjs --check
sha256sum docs/research/prompts/human-one-paste-v0.2.txt tests/Fixtures/research/conversational-review-v0.1.json
node --test tests/Scripts/conversational-pilot.test.mjs
```

Record the Git commit, actual prompt SHA-256 and packet SHA-256; `main` is a discoverability pointer, **not** an immutable scientific identifier. Changes to the prompt require a new version and regeneration.

## Human/AI provenance boundary

- **H0: independent human decision, conversational transcription.** The AI displays one complete preserved case at a time and asks for a numbered human decision plus a human-written justification. The mapping `1=present`, `2=absent (not observed in this material)`, `3=unknown`, `4=unavailable` is mechanical. Missing numbers or justifications trigger neutral clarification, not inferred labels. Raw answers, corrections and consent to hand off the transcript are preserved.
- **A0: LLM-generated suggestions**, governed by the distinct existing [A0 proposal](ai-annotation-comparison-proposal.md); must retain `ai_suggestion` provenance and must not be represented as H0.
- **HA: human assisted by model labels** is a third, potentially anchored condition; it does not satisfy the blind H0 reference requirement.
- **HTML baseline:** preserved-document, offline research-mode form, with detector metadata hidden. It is not replaced by adding this prompt.

No case content may be expanded with live-site data; sampled source URLs are provenance only. Preserved page text is untrusted content, not an instruction for the assistant. Do not submit restricted raw documents, screenshots, browser traces, cookies or incidental personal data to third-party AI vendors without the research-governance approval and a minimized authorized packet. Consent to transmit a participant's response to the researcher is obtained separately; model output alone is not consent.

The experimental conversation JSON is **not directly importable** with `review:import` and cannot become human gold by changing a field name. The interviewer cannot verify that the participant read the material unaided; record eligibility self-report and facilitator deviations. Different models or context truncation may alter how faithfully the protocol is followed.

## Usability pilot and observational record

The **official #195 pilot** requires the correct preserved PoC ResearchRun `01a10d65-a2ec-7279-b51f-f16990e95313`, run `37358645153`, restoration of source artifact hashes, a checked **reviewable** pilot slice (up to 10 cases or 20 active minutes), one deferred case and an eligible actual non-implementer. The PoC is documented as 94 cases/78 reviewable/16 deferred, but a documentation count is not a substitute for checking the archived original. Missing historical artifacts must not be silently replaced by the older 85-case public test packet or live recollection.

**The synthetic one-paste rehearsal cannot satisfy the official #195 gate.** Before an H0 conversation using real archival material could be accepted as the formal instrument, approve and version that change against the existing HTML pilot protocol; freeze the exact permitted evidence, rule text, model/platform and environment before collecting official labels. Never distribute restricted material merely because it is addressable through GitHub or was once visible publicly.

The human observer/facilitator captures, without editorial improvement:

| Construct | Observable record |
| --- | --- |
| Task completion | Reviewable attempted/completed, deferred case interpreted |
| Workload | Active time actually measured or self-reported, fatigue, repeated interactions, navigation obstacles |
| Clarity | Verbatim confusions about `absent`, `unknown`, `unavailable`, `present`, deferred and rationale |
| Evidence boundary | Attempts/need to open live sites, interpretation of provenance URL, incomplete material |
| Assistance | Exact facilitator clarifications, extra instructions, model deviations or suggested labels |
| Technical fidelity | Case IDs/order/count, unabridged preserved material, output fields, human confirmation, export errors |
| Self-report | Comprehension of labels rated 1–5, feedback, optional platform/model shown, authorization to share |

Record `null` for time or model ID that could not be measured/identified; do not invent stopwatch precision, UX benefit or participant observations. Keep raw transcript, case-level labels and personal data private. Report only minimized methodological observations publicly. If the pilot reveals material confusion, change and version the instrument, rerun technical tests and repeat human pilot where semantics/instructions changed.

## Potential contribution to the article (conditional)

The method is reproducible because the source fixture, interviewer protocol, generated prompt, commit and hashes are versioned. This can document **why the researchers designed a lower-friction annotation workflow**, even if the hypothesis is later refuted. It is not the main contribution of the first paper, whose research questions address measurement attrition, provenance/reproducibility and detector validity.

The author may report **as implemented**: “We prepared a versioned, self-contained conversational human-annotation facilitator that presents frozen evidence and transcribes explicitly selected human labels without exposing detector predictions. This was motivated by developer-reported burden with the existing form. A synthetic fixture and deterministic prompt-consistency tests exercised the handoff.”

The author **must not yet report**: “The prompt reduced human effort,” “the interface was homologated with independent reviewers,” “H0 equals human gold,” or “the new interaction improved inter-rater agreement.” Such claims require genuine human data.

A later comparison with HTML would need predeclared cases, equivalent evidence, participant eligibility, task ordering, model provenance and measured time/clarifications/comprehension. Any within-person crossover introduces learning/sequence effects; small pilot samples provide feasibility evidence, not reliable population-level effect estimates. Explore the usability outcome as **instrument validation**, not as a new RQ4 detector-accuracy result.