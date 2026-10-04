#!/bin/sh
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
TMP_DIR=$(mktemp -d)
SNAPSHOT="$TMP_DIR/ipb-anuario.html"
POPULATION="$TMP_DIR/ipb-population.csv"
SAMPLE="$TMP_DIR/ipb-sites.csv"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

cd "$ROOT_DIR"

printf '%s\n' '== Privacy Evidence: official IPB directory demo =='
printf '%s\n' 'Source discovery/extraction is external to the Privacy Evidence core.'
printf '%s\n' 'This is a live replication run; directory and website content can change.'

printf '\n%s\n' '== 1. External producer: preserve official directory snapshot =='
php examples/hacktoberfest/ipb/source.php fetch "$SNAPSHOT"

printf '\n%s\n' '== 2. External producer: convert snapshot to canonical CSV =='
php examples/hacktoberfest/ipb/source.php extract "$SNAPSHOT" "$POPULATION"

printf '\n%s\n' '== 3. Generic core: inspect and classify canonical population =='
php bin/privacy-evidence source:import "$POPULATION"

printf '\n%s\n' '== 4. Generic core: select deterministic institutional-website sample =='
php bin/privacy-evidence source:sample-websites "$POPULATION" "$SAMPLE" \
  --limit=8 \
  --seed=hacktoberfest-ipb-v1
cat "$SAMPLE"

printf '\n%s\n' '== 5. Generic core: run acquisition and evidence detectors =='
RUN_ID=$(php bin/privacy-evidence run "$SAMPLE")
printf 'Run ID: %s\n' "$RUN_ID"

printf '\n%s\n' '== 6. Show durable run status =='
php bin/privacy-evidence status "$RUN_ID"

printf '\n%s\n' '== 7. Apply all versioned regulatory profiles =='
php bin/privacy-evidence analyze "$RUN_ID"

printf '\n%s\n' '== 8. Generate reproducible exports and report =='
EXPORT_DIR=$(php bin/privacy-evidence report "$RUN_ID")
printf 'Export directory: %s\n' "$EXPORT_DIR"

printf '\n%s\n' '== Done =='
printf 'Run ID: %s\n' "$RUN_ID"
printf 'Inspect: %s\n' "$EXPORT_DIR"
