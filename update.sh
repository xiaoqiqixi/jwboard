#!/usr/bin/env bash

# JWBoard production updater. It deliberately never resets local files,
# rewrites .env, or runs `composer update`.
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
REMOTE_NAME="${JWBOARD_REMOTE:-origin}"

cd "$APP_DIR"

fail() {
  echo "Error: $*" >&2
  exit 1
}

read_version() {
  if [ -s "$APP_DIR/VERSION" ]; then
    tr -d '\r\n' < "$APP_DIR/VERSION"
  else
    echo "JWBoard (unknown version)"
  fi
}

command -v git >/dev/null 2>&1 || fail "Git is not installed."
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP executable not found: $PHP_BIN"

[ -f artisan ] || fail "Run this script from a JWBoard application directory."
[ -d .git ] || fail "This updater requires a Git checkout."
[ -f .env ] || fail ".env is missing; refusing to update an unconfigured installation."
[ -f composer.lock ] || fail "composer.lock is missing; refusing an update that could change dependency versions."

VERSION_BEFORE="$(read_version)"
echo "Current version: ${VERSION_BEFORE}"

PHP_VERSION_ID="$($PHP_BIN -r 'echo PHP_VERSION_ID;')"
if [ "$PHP_VERSION_ID" -lt 70400 ] || [ "$PHP_VERSION_ID" -ge 80000 ]; then
  fail "JWBoard requires PHP 7.4. Set PHP_BIN to your aaPanel PHP 7.4 binary."
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  fail "The working tree has local changes. Commit or back them up before updating."
fi

DEFAULT_BRANCH="$(git symbolic-ref --quiet --short "refs/remotes/${REMOTE_NAME}/HEAD" 2>/dev/null | sed "s#^${REMOTE_NAME}/##" || true)"
BRANCH="${JWBOARD_BRANCH:-${DEFAULT_BRANCH:-$(git branch --show-current)}}"
[ -n "$BRANCH" ] || fail "Unable to determine the update branch. Set JWBOARD_BRANCH explicitly."

echo "Fetching ${REMOTE_NAME}/${BRANCH}..."
git fetch --prune "$REMOTE_NAME" "$BRANCH"

LOCAL_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "${REMOTE_NAME}/${BRANCH}")"
if [ "$LOCAL_COMMIT" != "$REMOTE_COMMIT" ]; then
  if ! git merge-base --is-ancestor "$LOCAL_COMMIT" "$REMOTE_COMMIT"; then
    fail "Local history has diverged from ${REMOTE_NAME}/${BRANCH}; resolve it manually."
  fi
  echo "Fast-forwarding application code..."
  git merge --ff-only "${REMOTE_NAME}/${BRANCH}"
else
  echo "Code is already up to date; reconciling dependencies and database schema."
fi

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  COMPOSER_PATH="$(command -v "$COMPOSER_BIN")"
else
  COMPOSER_PATH="$APP_DIR/composer.phar"
  [ -f "$COMPOSER_PATH" ] || fail "Composer is unavailable. Install Composer or put composer.phar in ${APP_DIR}."
fi

mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs config/theme
chmod -R ug+rwX bootstrap/cache storage config/theme

echo "Installing locked PHP dependencies..."
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

[ -f vendor/autoload.php ] || fail "Composer did not create vendor/autoload.php."

echo "Applying JWBoard schema updates..."
"$PHP_BIN" artisan jwboard:update
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan horizon:terminate || true

VERSION_AFTER="$(read_version)"
echo "JWBoard update completed: ${VERSION_BEFORE} -> ${VERSION_AFTER}"
echo "Restart PHP-FPM from aaPanel if OPCache is enabled."
