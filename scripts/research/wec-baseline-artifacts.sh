#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

usage() {
  echo 'Usage: wec-baseline-artifacts.sh hash TARGET_DIR | count WEC_DIR EXPECTED_TARGETS' >&2
  exit 2
}

[ "$#" -ge 2 ] || usage
operation="$1"
root="$2"
[ -d "$root" ] || { echo "WEC directory not found: $root" >&2; exit 2; }

case "$operation" in
  hash)
    [ "$#" -eq 2 ] || usage
    # Write outside the traversal tree so the hash manifest never hashes itself.
    tmp_manifest="$(mktemp)"
    trap 'rm -f "$tmp_manifest"' EXIT
    find "$root" -type f ! -name files.sha256 -print0 \
      | sort -z \
      | xargs -0 -r sha256sum > "$tmp_manifest"
    mv "$tmp_manifest" "$root/files.sha256"
    trap - EXIT
    sha256sum --check --status "$root/files.sha256"
    ;;
  count)
    [ "$#" -eq 3 ] || usage
    expected="$3"
    [[ "$expected" =~ ^[1-9][0-9]*$ ]] || { echo 'Expected target count must be positive.' >&2; exit 2; }
    total=0
    successes=0
    failures=0
    for exit_file in "$root"/poc-*/exit-code.txt; do
      [ -f "$exit_file" ] || continue
      code="$(cat "$exit_file")"
      [[ "$code" =~ ^[0-9]+$ ]] || { echo "Invalid WEC exit code at $exit_file" >&2; exit 1; }
      total=$((total + 1))
      if [ "$code" -eq 0 ]; then
        successes=$((successes + 1))
      else
        failures=$((failures + 1))
      fi
    done
    # Both files exist even when the data gate fails; keep the diagnostic bundle.
    printf '%s\n' "$successes" > "$root/success-count.txt"
    printf '%s\n' "$failures" > "$root/failure-count.txt"
    if [ "$total" -ne "$expected" ]; then
      echo "WEC target accounting incomplete: expected $expected, found $total" >&2
      exit 1
    fi
    if [ "$successes" -eq 0 ]; then
      echo 'WEC produced zero successful target processes; experiment cannot be accepted.' >&2
      exit 1
    fi
    ;;
  *) usage ;;
esac
