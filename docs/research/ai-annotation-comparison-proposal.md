# Proposal: conversational and LLM-assisted evidence-review experiment

Status: **exploratory proposal, not a change to the frozen human-reference protocol**.
Related: #195 (instrument usability), #196 (sample design), #32/#33/#84 (human-reference evaluation).
Version: proposal 0.1.0, 2026-10-08.

## Research question and why this is separate

Would a conversational interface that is operated by different humans using different AI platforms reduce annotation workload while preserving traceable evidence decisions? Can LLM-generated labels approximate independent human decisions for *this exact privacy-evidence task*?

These are two distinct questions:

1. **Interface usability:** do genuine people understand the preserved evidence, labels, limitations and handoff? An LLM's report of its own usability is not a human observation.
2. **Measurement validity:** do labels match an independently adjudicated human reference? Agreement among models alone cannot establish validity.

The existing #195 pilot is **up to 10 reviewable cases or 20 active minutes**, not an assignment to read ~200 websites. The PoC's 94 evidence cases (78 reviewable, 16 deferred) are not the final #196 publication benchmark. Separately, the frozen full-IPB observation has **205 analytically observed canonical website units** (193 fully and 12 partially measured), but this is a website-level denominator, **not** 205 independent gold-label cases. Different signals/cases can share one site.

The request to replace the current HTML form with a prompt is evidence of a potential usability problem worth studying, not sufficient independent usability evidence for closing #195.

## Predeclared experimental conditions

Keep the current human requirement until a protocol change is explicitly reviewed and versioned.

- **H0 — independent human:** a non-implementer human sees only the same preserved source material and codebook, records an initial decision independently, and does not see LLM/detector predictions. The interface can be conversational if the AI **only reads scripted questions and transcribes the human's own stated labels**. Keep the human's original statements and subsequent structured export; never silently replace an ambiguous human answer with an AI inference. A conversational UI requires its own pilot/freeze before being substituted for the previously frozen UI.
- **A0 — LLM-only:** a named model receives the same permitted packet and frozen rubric and makes every decision without a human labeling it. The operator identifies the exact platform/model, attaches approved input, records deviations and returns the output. Store results as `ai_suggestion`, not `human`.
- **HA — AI-assisted human:** a person reviews LLM suggestions and may accept/edit/reject with per-case audit. This is a distinct, potentially *anchored* condition. Do not count passive acceptance as independent human ground truth, or pool HA labels with H0 without reporting the condition.
- Optional **A1/A2**: other independent model *versions* and providers running A0 with the identical input, prompt and decoding controls where available. Different operators or wrappers do not guarantee independent model error.

For a scientific final evaluation, prefer two blind H0 passes on a justified, adequately supported **overlap subset** plus an A0 comparison; use AI for triage/scale outside the gold subset where a human-validity claim is not made. A single adjudicator resolves disagreements only after human-human agreement has been frozen. A human-supervised-AI-only run is **not** a third human judge.

## Sequence and stop gates

1. **Safety/input authorization:** only public synthetic fixtures or a research-governance-approved minimized packet may be sent to third-party AI platforms. Restricted raw HTML, screenshots, browser storage, cookies, credentials and incidental personal data stay in restricted storage; public availability of a page does not automatically authorize republishing its full contents to model providers. Do not post case-level outputs publicly by default.
2. **Dry run without scientific claims:** run the common synthetic 10-reviewable/1-deferred fixture under `tests/Fixtures/research/conversational-review-v0.1.json` with the frozen `docs/research/prompts/conversational-review-v0.1.md`. It tests prompt comprehension, case accounting, correct abstention, export fidelity and operator feedback. It does *not* meet #195's real archived-material human pilot or #196's detector gold-data requirements.
3. **Document pilot usability:** have a human who did not implement the conversational instrument operate it, log confusion, intervention count, time and comprehension. For H0, ask the human for the label before any model answer is exposed; for A0, log the person as **facilitator/operator**, not a human adjudicator.
4. **Freeze before final data:** select one archival ResearchRun; require identical packet hashes and evidence scope across arms; version the rubric, prompt, model snapshots/settings (when controllable), transcript format, sampler seed, inclusion/exclusion reasons and expected per-signal support. Avoid using final labels to tune the detector or prompt.
5. **Evaluate:** report annotation agreement per signal and support (not only overall percentages), abstention/unknown/deferred, evidence-quote fidelity, model-model disagreement, human-human reliability before adjudication, model-to-human error, estimated costs, operator effort, observed bias and reviewer burden. Include rare-label uncertainty, repeated-site dependence and missing-evidence limitations. High agreement between A0 models is not independent ground truth.
6. **Go/no-go:** only consider replacing part of human annotation after a preregistered task-specific comparison against blind independent human reference on a sufficiently large and representative stratified set. A statistically justified equivalence/substitution claim is a separate decision, not something accomplished by rewriting the prompt. Preserve original #195/#196 gate semantics unless the revised protocol and claims are accepted.

## Provenance and classification

Record: experimental condition (H0/A0/HA); person ID (pseudonymous), role and how they participated; provider/tool exact model and release name or 'unknown'; approximate session time and duration; prompt version/hash, packet SHA-256/version, tool settings if visible, Web/file access availability; declared evidence IDs, questions, labels, cited preserved spans, rationales, uncertainty, human edits, abstentions, deviations, parse failures and raw transcript reference. An operator may supply model metadata; never allow the model to invent it.

The suggested text/JSON output is **not a drop-in input to `review:import`**. The current importer explicitly rejects AI identities for human gold annotations. Validate/map exploratory outputs in a future separate import path after review; do not remove that guardrail to unblock the experiment.

## Publication boundaries

- Can claim: feasibility and observed reliability/efficiency of a particular prompt/model on a declared task *after measurement*, with conditions and uncertainty.
- Cannot claim: human usability from AI simulation alone, that an LLM result is independent human gold, model superiority from 10 synthetic cases, or legal compliance from website evidence.

## Relevant peer-reviewed research

- Gilardi, Alizadeh & Kubli (2023), PNAS, **ChatGPT outperforms crowd workers for text-annotation tasks**, https://doi.org/10.1073/pnas.2305016120 — demonstrates potential task-dependent efficiency, with a human-built reference.
- Bavaresco et al. (2025), ACL, **LLMs instead of Human Judges? A Large Scale Empirical Study across 20 NLP Evaluation Tasks**, https://aclanthology.org/2025.acl-short.20/ — large variability by task/model.
- Calderon, Reichart & Dror (2025), ACL, **The Alternative Annotator Test for LLM-as-a-Judge**, https://aclanthology.org/2025.acl-long.782/ — possible substitution must be statistically tested using human-labeled reference.
- Schroeder, Roy & Kabbara (2025), Findings ACL, **Just Put a Human in the Loop?**, https://aclanthology.org/2025.findings-acl.1323/ — AI suggestions can alter human annotation distributions and inflate apparent model accuracy.
- Gu et al. (2026), Findings ACL, **Large Language Models Are Effective Human Annotation Assistants, But Not Good Independent Annotators**, https://aclanthology.org/2026.findings-acl.4/ — a task-specific example supporting hybrid, not universal AI-only replacement.
- ACM SIGSOFT Empirical Standards, https://www2.sigsoft.org/EmpiricalStandards/ — transparent sampling, annotation procedure, threats to validity, and reliability.

## Participant prompts

- LLM-generated annotations with a human operator (A0): [conversational-review-v0.1.md](prompts/conversational-review-v0.1.md).
- Independent human decisions with a neutral AI interviewer (H0 prototype): [human-guided-conversational-review-v0.1.md](prompts/human-guided-conversational-review-v0.1.md).

Both use the same **synthetic** fixture initially; do not combine H0/A0 results as though they have the same origin. Human role and model role must be logged explicitly. Study packet content, including preserved personal data, needs approval before being uploaded to outside tools.

## Current status

**Prepared:** protocol proposal, synthetic non-sensitive fixture, model-agnostic prompt and group invitation. **Not performed:** any human pilot, real-archive A0/H0 run, comparison with a frozen human gold set, approval to disclose restricted materials, or statistical test of annotator substitution. **#195/#196 remain open.**
