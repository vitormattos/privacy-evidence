#!/bin/sh
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
TMP_DIR=$(mktemp -d)
SNAPSHOT="$TMP_DIR/ipb-anuario.html"
SAMPLE="$TMP_DIR/ipb-sites.csv"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

cd "$ROOT_DIR"

printf '%s\n' '== Privacy Evidence: official IPB directory demo =='
printf '%s\n' 'Source of truth: official IPB/iCalvinus church directory.'
printf '%s\n' 'This is a live replication run; directory and website content can change.'

printf '\n%s\n' '== 1. Preserve the current official directory snapshot =='
bin/privacy-evidence source:fetch-ipb "$SNAPSHOT"

printf '\n%s\n' '== 2. Inspect and classify the full directory =='
bin/privacy-evidence source:import "$SNAPSHOT"

printf '\n%s\n' '== 3. Select a deterministic institutional-website sample =='
bin/privacy-evidence source:sample-websites "$SNAPSHOT" "$SAMPLE" \
  --limit=8 \
  --seed=hacktoberfest-ipb-v1
cat "$SAMPLE"

printf '\n%s\n' '== 4. Run acquisition and evidence detectors =='
RUN_ID=$(bin/privacy-evidence run "$SAMPLE")
printf 'Run ID: %s\n' "$RUN_ID"

printf '\n%s\n' '== 5. Show durable run status =='
bin/privacy-evidence status "$RUN_ID"

printf '\n%s\n' '== 6. Apply all versioned regulatory profiles =='
bin/privacy-evidence analyze "$RUN_ID"

printf '\n%s\n' '== 7. Generate reproducible exports and report =='
EXPORT_DIR=$(bin/privacy-evidence report "$RUN_ID")
printf 'Export directory: %s\n' "$EXPORT_DIR"

printf '\n%s\n' '== Done =='
printf 'Run ID: %s\n' "$RUN_ID"
printf 'Inspect: %s\n' "$EXPORT_DIR"
