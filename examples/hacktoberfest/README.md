<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Hacktoberfest PHP-native ML demo

This is a deterministic, network-free demonstration of the experimental PHP-native ML path.

Run from a clean checkout after `composer install`:

```bash
examples/hacktoberfest/run-demo.sh
```

The script first builds a small Rubix model from project-authored synthetic examples, then runs a separate inference command that **requires the saved model artifact**. The second command fails if that artifact is absent.

The demonstration intentionally uses a controller-identity sentence that the current transparent rule detector does not recognize. The local Rubix model supplies a second opinion. The disagreement is then written through the real auditable review integration as an `ai_suggestion`, while the case remains pending for a human reviewer.

The JSON output shows:

- deterministic detector state and version;
- ML probability, threshold and candidate state;
- exact model artifact SHA-256 and training provenance;
- disagreement/uncertainty assessment and deterministic review priority;
- pending review count and non-human suggestion record.

The synthetic examples are part of this repository and covered by the repository license. No external dataset, Python runtime, OpenAI API, Ollama server or remote inference service is required.

This is an experimental evidence-classification demonstration. It does not make a legal-compliance determination and it does not replace human review.

The real-user story required by the Weekend Challenge is documented in `docs/challenge/hacktoberfest-real-user-use-case.md`. It comes from the actual research workflow that motivated Privacy Evidence.


## Real-site validation

For a second, live demonstration using a small curated sample of public IPB websites, see `IPB_LIVE_DEMO.md` and run:

```bash
examples/hacktoberfest/run-ipb-live-demo.sh
```

This live sample exercises source ingestion, acquisition, evidence detection, regulatory-profile analysis and report generation. It is intentionally non-representative and must not be interpreted as a legal-compliance ranking of the listed churches.
