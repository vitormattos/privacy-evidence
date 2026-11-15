#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

# Create synthetic WEC/Privacy Evidence files for the real frozen sample.
# This fixture uses no network or real website evidence.
python3 - "$ROOT" "$TMP_ROOT" <<'PY'
import csv
import json
import pathlib
import sys

repo = pathlib.Path(sys.argv[1])
root = pathlib.Path(sys.argv[2])
config = json.loads((repo / "config/experiments/wec-baseline-v1.json").read_text())
with (repo / "examples/poc/sites.csv").open(newline="") as stream:
    sample = list(csv.DictReader(stream))
assert len(sample) == 12

def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2))

commit = "a" * 40
write_json(root / "freeze-manifest.json", {
    "sample": {"sha256": config["sample"]["sha256"]},
    "wec": {"sha256": config["wec"]["distributionSha256"]},
    "privacyEvidence": {"gitCommit": commit},
})
write_json(root / "privacy-evidence/exports/manifest.json", {
    "id": "synthetic-research-run", "gitCommit": commit,
    "protocolVersion": "0.1.0",
    "versions": {"php": "8.4.1"}, "configuration": {"synthetic": True},
})
(root / "privacy-evidence/run-id.txt").write_text("synthetic-research-run\n")
(root / "privacy-evidence/started-at.txt").write_text("2026-11-01T00:00:00Z\n")
(root / "privacy-evidence/finished-at.txt").write_text("2026-11-01T00:01:00Z\n")
population = []
outcomes = []
documents = []
for index, record in enumerate(sample):
    target_id = record["id"]
    url = record["url"]
    population.append({"resourceId": target_id, "sourceValue": url})
    ok = index > 0
    outcomes.append({
        "resourceId": target_id,
        "measurementStatus": "partially_measured" if ok else "not_measurable",
        "primaryReason": "budget_limited" if ok else "synthetic_timeout",
        "successfulDocuments": 1 if ok else 0,
    })
    if ok:
        documents.append({"resourceId": target_id, "artifactHash": "f" * 64})
    target = root / "wec" / target_id
    (target / "output").mkdir(parents=True)
    (target / "declared-url.txt").write_text(url + "\n")
    (target / "exit-code.txt").write_text("0\n")
    (target / "started-at.txt").write_text("2026-11-01T00:00:00Z\n")
    (target / "finished-at.txt").write_text("2026-11-01T00:00:05Z\n")
    (target / "stderr.log").write_text(
        '{"level":"error","message":"synthetic HTTP 403"}\n' if index == 11 else ""
    )
    write_json(target / "output/inspection.json", {
        "uri_ins": url, "browser": {"version": "synthetic-browser"},
        "script": {"version": {"npm": "3.3.0"}, "node_version": "v20", "config": {}},
        "cookies": [], "hosts": {"requests": {"thirdParty": []}},
    })
write_json(root / "privacy-evidence/exports/population-results.json", population)
write_json(root / "privacy-evidence/exports/resource-outcomes.json", outcomes)
write_json(root / "privacy-evidence/exports/documents.json", documents)
(root / "wec/started-at.txt").write_text("2026-11-01T00:00:00Z\n")
(root / "wec/finished-at.txt").write_text("2026-11-01T00:01:00Z\n")
(root / "tool-block-order.txt").write_text("privacy-evidence,wec\n")
PY

for id in $(seq -w 1 12); do
    bash "$ROOT/scripts/research/wec-baseline-artifacts.sh" hash "$TMP_ROOT/wec/poc-$id"
done
normalize() {
    php "$ROOT/scripts/research/normalize-wec-baseline.php" \
        "$TMP_ROOT" "$ROOT/examples/poc/sites.csv" 12 12345
}
normalize
python3 - "$TMP_ROOT" <<'PY'
import json
import pathlib
import sys
root = pathlib.Path(sys.argv[1])
summary = json.loads((root / "normalized/summary.json").read_text())
rows = json.loads((root / "normalized/comparison.json").read_text())
assert len(rows) == 24
assert summary["wec"]["successfulProcessWithInspection"] == 12
assert summary["wec"]["validNonSelfHashManifests"] == 12
assert summary["wec"]["targetsWithNativeHttp403Error"] == 1
assert summary["privacyEvidence"]["targetsWithSuccessfulDocuments"] == 11
assert rows[0]["cookies_observable"] is None
assert rows[0]["collection_succeeded"] is False
assert rows[1]["cookies_observable"] is True
assert rows[1]["wall_seconds"] == 5
PY

rm "$TMP_ROOT/wec/poc-12/exit-code.txt"
normalize
python3 - "$TMP_ROOT" <<'PY'
import json
import pathlib
import sys
rows = json.loads((pathlib.Path(sys.argv[1]) / "normalized/comparison.json").read_text())
assert rows[23]["collection_succeeded"] is False
assert rows[23]["terminal_reason"] == "missing_or_invalid_exit_code"
PY

rm "$TMP_ROOT/wec/poc-12/declared-url.txt"
if normalize > /dev/null 2>&1; then
    echo 'Normalizer must reject unmatched sample identity.' >&2
    exit 1
fi
echo 'WEC baseline synthetic normalization tests passed.'
