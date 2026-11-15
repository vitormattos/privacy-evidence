#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SEAL="$ROOT/scripts/research/wec-baseline-seal.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

umask 077
export WEC_BASELINE_ENCRYPTION_KEY="$(openssl rand -hex 32)"
python3 - "$TMP" <<'PY'
import pathlib
import sys
root = pathlib.Path(sys.argv[1])
(root / 'original').write_bytes((b'synthetic research bytes\x00\xff' * 10000) + b'END')
(root / 'empty').write_bytes(b'')
PY

php "$SEAL" encrypt "$TMP/original" "$TMP/sealed.pewec"
php "$SEAL" decrypt "$TMP/sealed.pewec" "$TMP/recovered"
cmp "$TMP/original" "$TMP/recovered"

php "$SEAL" encrypt "$TMP/empty" "$TMP/empty.pewec"
php "$SEAL" decrypt "$TMP/empty.pewec" "$TMP/empty.recovered"
cmp "$TMP/empty" "$TMP/empty.recovered"

# The ciphertext, including its final authentication frame, must be tamper-evident.
cp "$TMP/sealed.pewec" "$TMP/tampered.pewec"
printf '\x01' | dd of="$TMP/tampered.pewec" bs=1 seek=50 conv=notrunc status=none
if php "$SEAL" decrypt "$TMP/tampered.pewec" "$TMP/tampered.out" >/dev/null 2>&1; then
    echo 'Ciphertext tampering must fail.' >&2
    exit 1
fi
test ! -f "$TMP/tampered.out"
head -c 42 "$TMP/sealed.pewec" > "$TMP/truncated.pewec"
if php "$SEAL" decrypt "$TMP/truncated.pewec" "$TMP/truncated.out" >/dev/null 2>&1; then
    echo 'Truncated ciphertext must fail.' >&2
    exit 1
fi
test ! -f "$TMP/truncated.out"

export WEC_BASELINE_ENCRYPTION_KEY="$(openssl rand -hex 32)"
if php "$SEAL" decrypt "$TMP/sealed.pewec" "$TMP/wrongkey.out" >/dev/null 2>&1; then
    echo 'Incorrect decryption key must fail.' >&2
    exit 1
fi
test ! -f "$TMP/wrongkey.out"

unset WEC_BASELINE_ENCRYPTION_KEY
if php "$SEAL" encrypt "$TMP/original" "$TMP/withoutkey.out" >/dev/null 2>&1; then
    echo 'Missing key must prevent plaintext upload preparation.' >&2
    exit 1
fi
test ! -f "$TMP/withoutkey.out"
echo 'WEC artifact confidentiality/integrity tests passed.'
