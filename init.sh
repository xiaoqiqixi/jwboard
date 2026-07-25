#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
VERSION="$(tr -d '\r\n' < "$APP_DIR/VERSION" 2>/dev/null || echo 'JWBoard')"

cd "$APP_DIR"

# These directories are intentionally empty in the release archive, but Laravel
# needs them before Composer triggers artisan package discovery.
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs config/theme
chmod -R ug+rwX bootstrap/cache storage config/theme
if [ -f /etc/init.d/bt ] && id www >/dev/null 2>&1; then
  chown -R www:www storage bootstrap/cache config/theme
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "PHP executable not found: $PHP_BIN" >&2
  exit 1
fi

PHP_VERSION_ID="$($PHP_BIN -r 'echo PHP_VERSION_ID;')"
if [ "$PHP_VERSION_ID" -lt 70400 ] || [ "$PHP_VERSION_ID" -ge 80000 ]; then
  echo "This package expects PHP 7.4. Set PHP_BIN to your aaPanel PHP 7.4 binary." >&2
  exit 1
fi

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  COMPOSER_PATH="$(command -v "$COMPOSER_BIN")"
else
  COMPOSER_PATH="$APP_DIR/composer.phar"
  if [ ! -f "$COMPOSER_PATH" ]; then
    if command -v curl >/dev/null 2>&1; then
      curl -fsSL https://getcomposer.org/composer-stable.phar -o "$COMPOSER_PATH"
    elif command -v wget >/dev/null 2>&1; then
      wget -q https://getcomposer.org/composer-stable.phar -O "$COMPOSER_PATH"
    else
      echo "Composer is not installed and neither curl nor wget is available." >&2
      exit 1
    fi
  fi
fi

echo "Installing ${VERSION} with PHP 7.4-compatible dependencies…"
"$PHP_BIN" "$COMPOSER_PATH" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
  echo "Composer did not create vendor/autoload.php; installation aborted." >&2
  exit 1
fi

"$PHP_BIN" artisan jwboard:install
