# IPB selective-measurability result — 2026-10-05 full-population run

Analysis: `selective-measurability/1.0.0`

## Frozen provenance

- ResearchRun: `01a10a29-cf8d-7711-8e6d-ec954e592cc2`
- GitHub Actions workflow run: `37260583969`
- preserved workflow artifact: `ipb-full-validation` (artifact `11324489250`)
- artifact digest: `sha256:fec211aa0a9ab0898123e1c4f7018810d116e0a572784a5abbcfd925736620a2`
- run Git commit: `fa276f203ba6d95bfb0270b7fdfd71047d1c47f1`
- dataset SHA-256: `094e991b22293d78a11bc18ec2fb191d612b66ecad65145466d22108db4bf022`
- protocol: `0.1.0-draft`
- canonical website measurement units: 566
- measurable canonical units: 205
- non-measurable canonical units: 361
- overall measurability: 36.22%

The analysis procedure and predictors were frozen in #221 before these associations were inspected.

## Reconstruction note

The preserved workflow artifact predates the dedicated `attrition-results.json` export. Canonical-unit membership and measurement status were reconstructed from the preserved `population-results.json` using the same canonical identity and measurability semantics now implemented by `RunExporter` and `SelectiveMeasurabilityAnalyzer`.

No live-Web recollection was performed.

## Results

### Normalized URL scheme

All 566 canonical normalized URLs use `http` in this frozen run. The scheme predictor therefore has **no variation** and cannot test selective measurability.

This is a property of the preserved normalization output, not evidence that HTTP and HTTPS have equal measurability.

### Protocol resource type

| Resource type | Support | Measurable | Non-measurable | Measurability | Comparator | Difference | Odds ratio | Fisher p | Holm-adjusted p |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Institutional website | 512 | 190 | 322 | 37.11% | 27.78% | +9.33 pp | 1.53 | 0.1843 | 0.7373 |
| Third-party hosted page | 54 | 15 | 39 | 27.78% | 37.11% | -9.33 pp | 0.65 | 0.1843 | 0.7373 |

There is a descriptive difference of 9.33 percentage points between the two resource types. With only 54 third-party-hosted units, however, the frozen Fisher/Holm procedure does not provide inferential support for that association.

The complementary binary rows carry the same underlying 2x2 comparison in opposite directions. Reporting both is useful for the machine-readable category table, but they must not be interpreted as two independent findings.

### Duplicate-group membership

| Group | Support | Measurable | Non-measurable | Measurability | Comparator | Difference | Odds ratio | Fisher p | Holm-adjusted p |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Duplicate-group canonical unit | 8 | 3 | 5 | 37.50% | 36.20% | +1.30 pp | 1.06 | 1.0000 | 1.0000 |
| Singleton | 558 | 202 | 356 | 36.20% | 37.50% | -1.30 pp | 0.95 | 1.0000 | 1.0000 |

The duplicate-group comparison provides no evidence of a material association, and the duplicate-group support is only eight units.

## Conclusion for #194

For the **available predeclared predictors**, this frozen run does **not provide compelling evidence that measurability is selective**.

That conclusion is narrower than saying measurement loss is random. In particular:

- scheme is not testable because there is no variation;
- the resource-type comparison has a non-trivial descriptive difference but limited subgroup support and no inferential support after correction;
- duplicate-group membership shows almost no difference but has only eight grouped units;
- the current frozen export does not contain valid, complete pre-outcome hosting/provider or redirect-pattern predictors for all canonical units;
- unobserved characteristics may still be associated with measurement loss.

Therefore the threat to complete-case studies remains: the present analysis **fails to demonstrate selective measurability on the variables it can validly test**, but it also cannot justify an assumption that the measured subset is missing completely at random.

This result should be reported together with RQ2: missingness treatment materially changes estimates in this run (#193), even though the current predictor set does not identify a statistically supported selection pattern.

## Machine-readable outputs

- `ipb-2026-10-05-selective-measurability.json`
- `ipb-2026-10-05-selective-measurability.csv`

These are aggregate, non-restricted derived results. Raw acquired website artifacts are not committed.
