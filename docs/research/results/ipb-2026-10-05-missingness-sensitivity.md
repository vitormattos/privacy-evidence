# IPB missingness sensitivity result — 2026-10-05 full-population run

Analysis: `missingness-sensitivity/1.0.0`

## Frozen provenance

- ResearchRun: `01a10a29-cf8d-7711-8e6d-ec954e592cc2`
- GitHub Actions workflow run: `37260583969`
- preserved workflow artifact: `ipb-full-validation` (artifact `11324489250`)
- artifact digest: `sha256:fec211aa0a9ab0898123e1c4f7018810d116e0a572784a5abbcfd925736620a2`
- run Git commit: `fa276f203ba6d95bfb0270b7fdfd71047d1c47f1`
- dataset SHA-256: `094e991b22293d78a11bc18ec2fb191d612b66ecad65145466d22108db4bf022`
- protocol: `0.1.0-draft`
- source population: 2,993 records
- canonical website measurement units: 566

The primary outcomes were frozen in #220 before these associations were inspected:
`privacy_notice`, `privacy_law_reference`, `privacy_contact`, `rights_disclosure`, and `cookie_notice`.

## Reconstruction note

The preserved 2026-10-05 workflow artifact predates the dedicated `attrition-results.json` export introduced later. Canonical-unit membership was therefore reconstructed from the preserved `population-results.json` using the same rule now implemented by `RunExporter`: an eligible resource is canonical when its `websiteMeasurementCanonicalResourceId` equals its own `resourceId`.

Evidence states came from the preserved `evidence.json`. The sensitivity aggregation follows the frozen rule in `missingness-sensitivity.md`: any present observation wins; otherwise unresolved states win over absent; no observation is unresolved.

No live-Web recollection was performed.

## Results

| Outcome | Present | Absent | Unresolved | Coverage | Complete-case prevalence | Naive-negative prevalence | Absolute change | Relative change | Provenance-aware bounds |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Privacy notice | 41 | 223 | 302 | 46.64% | 15.53% | 7.24% | -8.29 pp | -53.36% | 7.24%–60.60% |
| Privacy-law reference | 18 | 246 | 302 | 46.64% | 6.82% | 3.18% | -3.64 pp | -53.36% | 3.18%–56.54% |
| Privacy contact | 10 | 254 | 302 | 46.64% | 3.79% | 1.77% | -2.02 pp | -53.36% | 1.77%–55.12% |
| Rights disclosure | 11 | 253 | 302 | 46.64% | 4.17% | 1.94% | -2.22 pp | -53.36% | 1.94%–55.30% |
| Cookie notice | 40 | 224 | 302 | 46.64% | 15.15% | 7.07% | -8.08 pp | -53.36% | 7.07%–60.42% |

All five predeclared outcomes are classified as **unstable across missingness treatments** for this run.

The common relative reduction is not an independent repeated effect: the five outcomes share the same 264 resolved / 302 unresolved canonical-resource split. The signal-specific absolute change differs because the observed positive count differs by outcome.

## Interpretation of P2

For this frozen IPB run, proposition P2 is **supported**.

Treating unresolved units as negative cuts every selected prevalence estimate by approximately 53.36% relative to complete-case analysis. Only 46.64% of canonical units are resolved for these outcomes, and the provenance-aware identification bounds remain wide.

The strongest defensible conclusion is therefore methodological: **the substantive estimates for this run are sensitive to how non-observability is represented**. The result supports the project's rule that `unknown` must not be silently interpreted as `absent`.

This does not establish:
- that the unresolved observations are missing at random or not at random;
- the causal reason for measurement loss;
- the true population prevalence of the privacy signals;
- detector validity against human ground truth;
- generalization beyond this frozen IPB run.

Selective measurability is evaluated separately in #194.

## Machine-readable outputs

- `ipb-2026-10-05-missingness-sensitivity.json`
- `ipb-2026-10-05-missingness-sensitivity.csv`

These are aggregate, non-restricted derived results. Raw acquired website artifacts are not committed.
