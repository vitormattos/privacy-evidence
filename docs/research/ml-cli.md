# Experimental PHP-native ML CLI

The ML CLI is an experimental research interface. It trains candidate evidence-signal models on **external development data** and performs local inference from a saved Rubix artifact. It does not evaluate legal compliance and it does not write human ground truth.

## Train one signal

Use a prepared split produced from the external dataset workflow:

```bash
bin/privacy-evidence experiment:ml:train-signal \
  data/derived/claudinha/train.jsonl \
  data/derived/claudinha/claudinha-v1.manifest.json \
  controller_identity \
  data/models/controller-identity.rbx \
  2026-10-03T12:00:00Z
```

The training timestamp is explicit rather than read from the clock so the command's provenance is reproducible. Training refuses manifests whose purpose is not `external-development-data`, and it requires the manifest mapping version to match the code.

The command writes the RBX artifact and its `.metadata.json` sidecar, then emits JSON containing the artifact SHA-256, sample count and model provenance.

## Infer

```bash
bin/privacy-evidence experiment:ml:infer-signal \
  data/models/controller-identity.rbx \
  "The controller of your personal data is Example Ltd." \
  --threshold=0.5
```

Inference loads the existing artifact without retraining and emits machine-readable JSON with:

- candidate signal;
- positive-class probability;
- threshold and candidate state;
- artifact SHA-256;
- dataset, split, mapping, pipeline, estimator and Rubix provenance.

Both commands run entirely in PHP and require no remote inference service.
