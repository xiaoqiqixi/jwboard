#!/usr/bin/env bash

# JWBoard self-contained production updater.
# It updates from the newest GitHub Release and never requires this directory
# to be a Git checkout. Keep .env and runtime/customer data untouched.
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
REPOSITORY="${JWBOARD_REPOSITORY:-xiaoqiqixi/jwboard}"
API_URL="https://api.github.com/repos/${REPOSITORY}/releases/latest"

fail() { echo "Error: $*" >&2; exit 1; }
cleanup() { [ -n "${WORK_DIR:-}" ] && [ -d "$WORK_DIR" ] && rm -rf "$WORK_DIR"; }
trap cleanup EXIT

download() {
  local url="$1" output="$2"
  if command -v curl >/dev/null 2>&1; then
    curl -fL --retry 3 --connect-timeout 15 "$url" -o "$output"
  elif command -v wget >/dev/null 2>&1; then
    wget -q --tries=3 --timeout=15 "$url" -O "$output"
  else
    fail "curl or wget is required to download JWBoard releases."
  fi
}

read_version() {
  if [ -s "$APP_DIR/VERSION" ]; then
    sed -nE 's/.*([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' "$APP_DIR/VERSION" | head -n1
  fi
}

cd "$APP_DIR"
[ -f .env ] || fail ".env is missing; refusing to update an unconfigured installation."
[ -f artisan ] || fail "Run this script from the JWBoard application directory."
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP executable not found: $PHP_BIN"
command -v tar >/dev/null 2>&1 || fail "tar is required to unpack a JWBoard release."

PHP_VERSION_ID="$($PHP_BIN -r 'echo PHP_VERSION_ID;')"
if [ "$PHP_VERSION_ID" -lt 70400 ] || [ "$PHP_VERSION_ID" -ge 80000 ]; then
  fail "JWBoard requires PHP 7.4. Set PHP_BIN to your aaPanel PHP 7.4 binary."
fi

VERSION_BEFORE="$(read_version || true)"
echo "Current version: ${VERSION_BEFORE:-unknown}"
echo "Checking GitHub Release for ${REPOSITORY}..."

WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/jwboard-update.XXXXXX")"
RELEASE_JSON="$WORK_DIR/release.json"
download "$API_URL" "$RELEASE_JSON"

TAG="$(sed -nE 's/^[[:space:]]*"tag_name"[[:space:]]*:[[:space:]]*"([^"]+)".*/\1/p' "$RELEASE_JSON" | head -n1)"
[ -n "$TAG" ] || fail "GitHub did not return a usable release tag."
VERSION_REMOTE="$(printf '%s' "$TAG" | sed -nE 's/.*([0-9]+\.[0-9]+\.[0-9]+).*/\1/p')"
[ -n "$VERSION_REMOTE" ] || fail "Release tag ${TAG} does not contain a semantic version."

if [ -n "$VERSION_BEFORE" ] && "$PHP_BIN" -r 'exit(version_compare($argv[1], $argv[2], ">=") ? 0 : 1);' "$VERSION_BEFORE" "$VERSION_REMOTE"; then
  echo "JWBoard is already at ${VERSION_BEFORE}; no code download is required."
  exit 0
fi

echo "Downloading JWBoard ${VERSION_REMOTE} (${TAG})..."
ARCHIVE="$WORK_DIR/release.tar.gz"
download "https://github.com/${REPOSITORY}/archive/refs/tags/${TAG}.tar.gz" "$ARCHIVE"
tar -xzf "$ARCHIVE" -C "$WORK_DIR"
RELEASE_DIR="$(find "$WORK_DIR" -mindepth 1 -maxdepth 1 -type d -name "${REPOSITORY##*/}-*" | head -n1)"
[ -n "$RELEASE_DIR" ] && [ -f "$RELEASE_DIR/artisan" ] || fail "The downloaded release archive is incomplete."

VERSION_PACKAGE="$(sed -nE 's/.*([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' "$RELEASE_DIR/VERSION" | head -n1)"
[ "$VERSION_PACKAGE" = "$VERSION_REMOTE" ] || fail "Release tag ${TAG} and package VERSION do not match."

# Copy source over the installation without touching credentials, customer data,
# installed dependencies, or Laravel's writable cache directories.
shopt -s dotglob nullglob
for source in "$RELEASE_DIR"/*; do
  name="$(basename "$source")"
  case "$name" in
    .env|.git|storage|vendor) continue ;;
    bootstrap)
      mkdir -p "$APP_DIR/bootstrap"
      for bootstrap_file in "$source"/*; do
        [ "$(basename "$bootstrap_file")" = "cache" ] || cp -a "$bootstrap_file" "$APP_DIR/bootstrap/"
      done
      ;;
    config)
      mkdir -p "$APP_DIR/config"
      for config_file in "$source"/*; do
        [ "$(basename "$config_file")" = "theme" ] || cp -a "$config_file" "$APP_DIR/config/"
      done
      ;;
    *) cp -a "$source" "$APP_DIR/" ;;
  esac
done

mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs config/theme
chmod -R ug+rwX bootstrap/cache storage config/theme
if [ -f /etc/init.d/bt ] && id www >/dev/null 2>&1; then
  chown -R www:www storage bootstrap/cache config/theme
fi

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  COMPOSER_PATH="$(command -v "$COMPOSER_BIN")"
else
  COMPOSER_PATH="$APP_DIR/composer.phar"
  [ -f "$COMPOSER_PATH" ] || download "https://getcomposer.org/composer-stable.phar" "$COMPOSER_PATH"
fi

echo "Installing release-locked PHP dependencies..."
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
[ -f vendor/autoload.php ] || fail "Composer did not create vendor/autoload.php."

echo "Applying JWBoard database updates..."
"$PHP_BIN" artisan jwboard:update
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan horizon:terminate || true

echo "JWBoard update completed: ${VERSION_BEFORE:-unknown} -> ${VERSION_REMOTE}"
echo "Release notes: https://github.com/${REPOSITORY}/releases/tag/${TAG}"
echo "Restart PHP-FPM from aaPanel if OPCache is enabled."
