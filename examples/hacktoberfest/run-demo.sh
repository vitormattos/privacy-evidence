#!/bin/sh
# SPDX-FileCopyrightText: 2026 Vitor Mattos
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
TMP_DIR=$(mktemp -d)
ARTIFACT="$TMP_DIR/controller-identity.rbx"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

cd "$ROOT_DIR"

bin/privacy-evidence experiment:hacktoberfest:build-model "$ARTIFACT"
bin/privacy-evidence experiment:hacktoberfest:demo "$ARTIFACT"
