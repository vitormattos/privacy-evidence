<!-- SPDX-FileCopyrightText: 2026 Vitor Mattos -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Publishing reviewer HTML on GitHub Pages

GitHub Pages is a delivery channel for the existing self-contained reviewer HTML. It does not receive annotations. Reviewers export JSON and return it through the agreed channel. Browser drafts are local to that browser, packet hash, mode and pathname; moving from an offline file to Pages does not migrate prior progress.

Repository Settings → Pages must use **GitHub Actions** as the source. `.github/workflows/pages.yml` tests the approved site on pull requests and deploys `site/` from `main` when those files change or when manually dispatched. It does not deploy PR content. The Pages environment's protection rules still apply.

The initial public packet is explicitly designated publishable: `site/runs/01a0fe51-0926-7f3c-9353-3185879b900a/reviewer-test.html`, the blank test HTML supplied and approved for publication by the maintainer. It contains the 85 selected PoC cases and their public source/provenance metadata, with no preserved page bodies and no human answers. It is a form test, not a usable historical research review. No downloaded test answers, real reviewer packet, raw artifacts, browser traces or SQLite database are included. The generated application HTML carries the project's AGPL notice; publishing it does not assign that license to third-party source pages.

For a future approved packet:

1. Prepare sufficient review material under the [gold-dataset workflow](../research/gold-dataset.md). Decide whether the embedded material may be published publicly before placing it in `site/`.
2. Generate a blank HTML at a new stable run/packet path. Use `--test-mode` only for tests. Never publish returned JSON or answered HTML.
3. Add its link to `site/index.html` and validate the actual new packet in the browser test before merging. Leave existing review URLs unchanged during an annotation round. Retire old packets explicitly when necessary; all site files remain public while deployed.

The workflow uploads only `site/`, not the repository, `data/` or its research documentation. The browser smoke test uses a local project subpath, traverses all 85 test cases, checks the exported test marker and unchanged provenance, and captures screenshots. No source website is visited by that test. Collection, detector, schema and annotation semantics are unchanged by this hosting setup.
