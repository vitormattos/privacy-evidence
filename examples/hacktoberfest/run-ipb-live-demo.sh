#!/bin/sh
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
DATASET="$ROOT_DIR/examples/hacktoberfest/ipb-live-demo-sites.csv"

cd "$ROOT_DIR"

printf '%s\n' '== Privacy Evidence: IPB live-site demo =='
printf '%s\n' 'This is a live replication run. Website content can change over time.'
printf '\n%s\n' '== 1. Inspect normalized source dataset =='
bin/privacy-evidence source:import "$DATASET"

printf '\n%s\n' '== 2. Run acquisition and evidence detectors =='
RUN_ID=$(bin/privacy-evidence run "$DATASET")
printf 'Run ID: %s\n' "$RUN_ID"

printf '\n%s\n' '== 3. Show durable run status =='
bin/privacy-evidence status "$RUN_ID"

printf '\n%s\n' '== 4. Apply all versioned regulatory profiles =='
bin/privacy-evidence analyze "$RUN_ID"

printf '\n%s\n' '== 5. Generate reproducible exports and report =='
EXPORT_DIR=$(bin/privacy-evidence report "$RUN_ID")
printf 'Export directory: %s\n' "$EXPORT_DIR"

printf '\n%s\n' '== Done =='
printf 'Run ID: %s\n' "$RUN_ID"
printf 'Inspect: %s\n' "$EXPORT_DIR"
