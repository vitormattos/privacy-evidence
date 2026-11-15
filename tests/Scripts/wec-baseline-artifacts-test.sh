#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail
SOURCE_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$SOURCE_ROOT/scripts/research/wec-baseline-artifacts.sh"
TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

fixture() {
  local root="$1" success="$2" index
  mkdir -p "$root"
  for index in $(seq -w 1 12); do
    mkdir -p "$root/poc-$index"
    if [ "$((10#$index))" -le "$success" ]; then
      printf '0\n' > "$root/poc-$index/exit-code.txt"
    else
      printf '1\n' > "$root/poc-$index/exit-code.txt"
    fi
  done
}

fixture "$TMP_ROOT/all-pass" 12
bash "$SCRIPT" count "$TMP_ROOT/all-pass" 12
test "$(cat "$TMP_ROOT/all-pass/success-count.txt")" = 12
test "$(cat "$TMP_ROOT/all-pass/failure-count.txt")" = 0

fixture "$TMP_ROOT/mixed" 7
bash "$SCRIPT" count "$TMP_ROOT/mixed" 12
test "$(cat "$TMP_ROOT/mixed/success-count.txt")" = 7
test "$(cat "$TMP_ROOT/mixed/failure-count.txt")" = 5

fixture "$TMP_ROOT/all-fail" 0
if bash "$SCRIPT" count "$TMP_ROOT/all-fail" 12 2>/dev/null; then
  echo 'All-failure case must block acceptance.' >&2
  exit 1
fi
test "$(cat "$TMP_ROOT/all-fail/success-count.txt")" = 0
test "$(cat "$TMP_ROOT/all-fail/failure-count.txt")" = 12

rm "$TMP_ROOT/mixed/poc-12/exit-code.txt"
if bash "$SCRIPT" count "$TMP_ROOT/mixed" 12 2>/dev/null; then
  echo 'Missing target outcome must block acceptance.' >&2
  exit 1
fi

printf 'invalid\n' > "$TMP_ROOT/mixed/poc-12/exit-code.txt"
if bash "$SCRIPT" count "$TMP_ROOT/mixed" 12 2>/dev/null; then
  echo 'Malformed exit code must block acceptance.' >&2
  exit 1
fi

HASH_DIR="$TMP_ROOT/hash-case"
mkdir -p "$HASH_DIR/output"
printf 'synthetic\n' > "$HASH_DIR/output/inspection.json"
printf 'outdated\n' > "$HASH_DIR/files.sha256"
bash "$SCRIPT" hash "$HASH_DIR"
! grep -q 'files.sha256' "$HASH_DIR/files.sha256"
grep -q 'inspection.json' "$HASH_DIR/files.sha256"
sha256sum --check --status "$HASH_DIR/files.sha256"
echo 'WEC baseline artifact accounting tests passed.'
