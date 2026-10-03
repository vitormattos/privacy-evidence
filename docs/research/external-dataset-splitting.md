# External dataset splitting

External ML datasets must be partitioned without leaking duplicated paragraphs or source policies across train/validation/development partitions.

The Claudinha preparation path uses a deterministic grouped SHA-256 split:

- duplicated annotation rows are first reconstructed into one multi-label paragraph sample;
- `companyId` is the source-policy grouping key when present;
- `paragraphId` is the fallback group for records without a company/source identifier;
- a recorded seed and stable SHA-256 group assignment determine the partition;
- normalized duplicate text appearing in different partitions is rejected;
- label support and SHA-256 are recorded for every partition;
- the dataset manifest records the split strategy, ratios, seed, split hash, and Claudinha mapping version.

Default ratios are 70% train, 15% validation and 15% development. These are partitions of **external development data** only. They are not the independently reviewed final Privacy Evidence gold dataset.

The split hash identifies the grouping assignments, seed and ratios. Identical input grouping and seed therefore produce the same split identity independent of filesystem location.
