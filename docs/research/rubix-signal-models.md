# Experimental per-signal Rubix models

Status: **experimental**

Privacy Evidence trains one binary model per observable evidence signal. A model predicts a candidate evidence signal only; it does not produce a legal or regulatory verdict.

## Initial estimator

The first working path uses Rubix ML `GaussianNB` on the fitted TF-IDF feature pipeline. It was selected as a deterministic, probabilistic and persistable baseline with continuous-feature support. This is an implementation baseline, not a claim that Gaussian Naive Bayes is the best estimator for privacy-policy text. #108 compares it with the deterministic rules and the internal Naive Bayes baseline before any promotion decision.

Each model requires both positive and negative training examples. Prediction exposes the positive-class probability and an explicit caller-controlled threshold.

## Artifact provenance

A saved model consists of:

- the RBX artifact containing the fitted feature pipeline and trained estimator;
- a JSON metadata sidecar containing the SHA-256 of the exact artifact and project-owned provenance.

Metadata records:

- evidence signal;
- external dataset identity/version/SHA-256;
- split SHA-256;
- label-mapping version;
- feature-pipeline version;
- estimator class;
- Rubix version;
- explicit UTC training timestamp.

Loading verifies the sidecar SHA-256 before RBX deserialization, then verifies that sidecar provenance matches the embedded model metadata. Broader artifact-size and trust-boundary hardening remains part of #110.

The artifact stores the fitted feature pipeline and estimator together so inference never silently refits preprocessing.
