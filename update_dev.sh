#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

./update.sh
